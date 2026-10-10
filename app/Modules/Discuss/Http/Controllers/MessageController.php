<?php

namespace App\Modules\Discuss\Http\Controllers;

use App\Modules\Discuss\Data\SendMessageData;
use App\Modules\Discuss\Http\Requests\SendMessageRequest;
use App\Modules\Discuss\Http\Requests\UpdateMessageRequest;
use App\Modules\Discuss\Http\Resources\MessageResource;
use App\Modules\Discuss\Models\Room;
use App\Modules\Discuss\Services\MemberService;
use App\Modules\Discuss\Services\ImageThumbnailService;
use App\Modules\Discuss\Services\MessageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Facades\Storage;

class MessageController
{
    public function __construct(
        private readonly MessageService $messageService,
        private readonly MemberService $memberService,
    ) {}

    public function index(Request $request, string $slug): ResourceCollection
    {
        $room = Room::where('slug', $slug)->firstOrFail();

        if ($room->type->value !== 'public') {
            abort_unless($this->memberService->isMember($room->id, $request->user()->id), 403);
        }

        $messages = \App\Modules\Discuss\Models\Message::where('room_id', $room->id)
            ->notDeleted()
            ->orderBy('created_at', 'desc')
            ->paginate(50);

        return MessageResource::collection($messages);
    }

    public function store(SendMessageRequest $request, string $slug): JsonResponse
    {
        $room = Room::where('slug', $slug)->firstOrFail();

        // A directly-uploaded image / document / video (multipart) takes
        // precedence over the `attachments` array of already-hosted URLs: it is
        // stored on the public disk and the message type follows the upload.
        // The caption is whatever was typed in `body`.
        $type = $request->input('type', 'text');
        $attachments = $request->input('attachments', []);
        $metadata = $request->input('metadata', []);

        $upload = $this->storeAttachment($request);
        if ($upload) {
            $type = $upload['type'];
            $attachments = [$upload['url']];
            $metadata = array_merge($metadata, $upload['metadata']);
        }

        $data = new SendMessageData(
            roomId: $room->id,
            userId: $request->user()->id,
            replyToId: $request->input('reply_to_id'),
            type: $type,
            body: $request->input('body'),
            attachments: $attachments,
            metadata: $metadata,
        );

        try {
            $message = $this->messageService->send($data);
        } catch (\Throwable $e) {
            // Send rejected (banned / muted) — don't leave the upload orphaned.
            $this->discardUpload($upload);
            throw $e;
        }

        return response()->json([
            'message' => new MessageResource($message),
        ], 201);
    }

    public function update(UpdateMessageRequest $request, string $slug, string $messageId): JsonResponse
    {
        $message = \App\Modules\Discuss\Models\Message::findOrFail($messageId);

        // Optional new attachment (multipart). Same storage layout as store().
        $upload = $this->storeAttachment($request);
        $attachment = $upload
            ? ['type' => $upload['type'], 'url' => $upload['url'], 'metadata' => $upload['metadata']]
            : null;

        try {
            $updated = $this->messageService->edit(
                $message,
                $request->input('body'),
                $request->user()->id,
                $attachment,
            );
        } catch (\Throwable $e) {
            // Edit rejected (e.g. not the owner) — don't leave the upload orphaned.
            $this->discardUpload($upload);
            throw $e;
        }

        return response()->json([
            'message' => new MessageResource($updated),
        ]);
    }

    public function destroy(string $slug, string $messageId): JsonResponse
    {
        $message = \App\Modules\Discuss\Models\Message::findOrFail($messageId);
        $this->messageService->delete($message, request()->user()->id);

        return response()->json(['message' => 'Message deleted.']);
    }

    /**
     * Stores an uploaded image / document / video (plus a video's poster frame)
     * on the public disk. `paths` lists everything written so callers can clean
     * up if the message ends up being rejected.
     *
     * @return array{type: string, url: string, metadata: array<string, mixed>, paths: list<string>}|null
     */
    private function storeAttachment(Request $request): ?array
    {
        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $path = $image->store('messages', 'public');
            $paths = [$path];

            // Small JPEG for the chat bubble (the browser then lazy-loads it
            // instead of the full-resolution original). Best effort: if GD
            // can't decode the source, the client just falls back to the
            // original file as the thumbnail.
            $metadata = [];
            $thumbBinary = $this->makeImageThumbnail((string) file_get_contents($image->getRealPath()));
            if ($thumbBinary !== null) {
                $thumbPath = 'messages/thumbs/' . pathinfo($path, PATHINFO_FILENAME) . '.jpg';
                Storage::disk('public')->put($thumbPath, $thumbBinary);
                $paths[] = $thumbPath;
                $metadata['thumbnail'] = Storage::url($thumbPath);
            }

            return ['type' => 'image', 'url' => Storage::url($path), 'metadata' => $metadata, 'paths' => $paths];
        }

        if ($request->hasFile('file')) {
            // Stored under a hashed name; the original name/size/mime go into
            // metadata so the client can show and download it under its real name.
            $file = $request->file('file');
            $size = $file->getSize();
            $path = $file->store('messages/files', 'public');

            return [
                'type' => 'file',
                'url' => Storage::url($path),
                'metadata' => [
                    'file' => [
                        'name' => $file->getClientOriginalName(),
                        'size' => $size,
                        'mime' => $file->getClientMimeType(),
                    ],
                ],
                'paths' => [$path],
            ];
        }

        if ($request->hasFile('video')) {
            $video = $request->file('video');
            $size = $video->getSize();
            $path = $video->store('messages/videos', 'public');
            $paths = [$path];

            $info = [
                'name' => $video->getClientOriginalName(),
                'size' => $size,
                'mime' => $video->getClientMimeType(),
                // Read by the browser (the server has no ffmpeg); cosmetic only.
                'duration' => $request->filled('duration') ? round((float) $request->input('duration'), 2) : null,
                'width' => $request->filled('width') ? (int) $request->input('width') : null,
                'height' => $request->filled('height') ? (int) $request->input('height') : null,
                'thumbnail' => null,
            ];

            if ($request->hasFile('thumbnail')) {
                $thumb = $request->file('thumbnail')->store('messages/thumbs', 'public');
                $paths[] = $thumb;
                $info['thumbnail'] = Storage::url($thumb);
            }

            return ['type' => 'video', 'url' => Storage::url($path), 'metadata' => ['video' => $info], 'paths' => $paths];
        }

        return null;
    }

    /** @param array{paths: list<string>}|null $upload */
    private function discardUpload(?array $upload): void
    {
        if ($upload) {
            Storage::disk('public')->delete($upload['paths']);
        }
    }

    /**
     * Resizes image bytes down to fit within 480×480 and re-encodes as JPEG,
     * for a lightweight chat-bubble thumbnail. Returns null if GD can't
     * decode the source (corrupt file, or an image format the GD build
     * doesn't support) — the caller then just skips the thumbnail.
     */
    private function makeImageThumbnail(string $binary): ?string
    {
        return app(ImageThumbnailService::class)->make($binary, 480);
    }
}
