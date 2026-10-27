<?php

namespace App\Modules\Discuss\Http\Controllers;

use App\Modules\Discuss\Http\Requests\PostCommentRequest;
use App\Modules\Discuss\Http\Requests\ReactToCommentRequest;
use App\Modules\Discuss\Http\Resources\MessageResource;
use App\Modules\Discuss\Models\Message;
use App\Modules\Discuss\Models\Room;
use App\Modules\Discuss\Services\CommentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Komentar halaman baca komik. Semua endpoint diidentifikasi lewat slug komik + index chapter;
 * room grup diskusinya diturunkan server (lihat CommentService).
 */
class CommentController
{
    public function __construct(
        private readonly CommentService $comments,
    ) {}

    public function index(Request $request, string $slug, int $index): JsonResponse
    {
        $result = $this->comments->list(
            $request->user()->id,
            $slug,
            $index,
            (int) $request->query('page', 1),
        );

        if (! $result['room']) {
            // Komik ini belum punya grup diskusi, jadi belum ada komentar.
            return response()->json([
                'available' => false,
                'room' => null,
                'data' => [],
                'meta' => ['current_page' => 1, 'last_page' => 1, 'total' => 0],
                'emotes' => [],
            ]);
        }

        $paginator = $result['paginator'];

        return response()->json([
            'available' => true,
            'room' => $this->roomPayload($result['room']),
            'data' => $this->present($request, $paginator->getCollection(), $result['thread_counts']),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ],
            'emotes' => $result['emotes'],
        ]);
    }

    public function replies(Request $request, string $slug, int $index, string $messageId): JsonResponse
    {
        $result = $this->comments->replies($request->user()->id, $slug, $index, $messageId);

        return response()->json([
            'data' => $this->present($request, $result['messages']),
            'total' => $result['total'],
            'room' => $this->roomPayload($result['room']),
        ]);
    }

    public function store(PostCommentRequest $request, string $slug, int $index): JsonResponse
    {
        $message = $this->comments->post(
            $request->user()->id,
            $slug,
            $index,
            (string) $request->input('body'),
            $request->input('reply_to_id'),
        );

        return response()->json([
            'message' => $this->present($request, collect([$message]))[0],
            'room' => $this->roomPayload(Room::findOrFail($message->room_id)),
        ], 201);
    }

    public function react(ReactToCommentRequest $request, string $slug, int $index, string $messageId): JsonResponse
    {
        $this->comments->react(
            $request->user()->id,
            $slug,
            $index,
            $messageId,
            (string) $request->input('emote_id'),
        );

        return response()->json(['message' => 'Reaction toggled.']);
    }

    /**
     * Bentuk pesan sama persis dengan Discuss (MessageResource), ditambah `thread_reply_count`
     * (jumlah semua balasan di bawah komentar, dari kolom komentar maupun dari chat Discuss).
     *
     * @param  \Illuminate\Support\Collection<int, Message>  $messages
     * @param  array<string, int>  $threadCounts
     * @return list<array<string, mixed>>
     */
    private function present(Request $request, $messages, array $threadCounts = []): array
    {
        return array_map(
            fn (array $item) => $item + ['thread_reply_count' => $threadCounts[$item['id']] ?? 0],
            MessageResource::collection($messages)->toArray($request),
        );
    }

    /** @return array{slug: string, name: string} */
    private function roomPayload(Room $room): array
    {
        return ['slug' => $room->slug, 'name' => $room->name];
    }
}
