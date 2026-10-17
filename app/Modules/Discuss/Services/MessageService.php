<?php

namespace App\Modules\Discuss\Services;

use App\Modules\Discuss\Contracts\MessageServiceContract;
use App\Modules\Discuss\Data\SendMessageData;
use App\Modules\Discuss\Enums\MessageType;
use App\Modules\Discuss\Events\MessageDeleted;
use App\Modules\Discuss\Events\MessageEdited;
use App\Modules\Discuss\Events\MessageSent;
use App\Modules\Discuss\Models\Member;
use App\Modules\Discuss\Models\Message;
use App\Modules\Discuss\Models\Room;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class MessageService implements MessageServiceContract
{
    public function __construct(
        private readonly MessageEntityService $entities,
    ) {}

    public function send(SendMessageData $data): Message
    {
        $member = Member::where('room_id', $data->roomId)
            ->where('user_id', $data->userId)
            ->first();

        if (! $member || $member->is_banned) {
            throw ValidationException::withMessages([
                'message' => ['You are not allowed to send messages in this room.'],
            ]);
        }

        if ($member->muted_until && $member->muted_until->isFuture()) {
            throw ValidationException::withMessages([
                'message' => ['You are muted until ' . $member->muted_until->format('Y-m-d H:i')],
            ]);
        }

        // Pesan sistem hanya dibuat server. Request sudah menolaknya; ini lapisan kedua
        // kalau ada pemanggil lain yang lupa memfilter.
        if ($data->type === MessageType::System->value) {
            throw ValidationException::withMessages([
                'type' => ['This message type cannot be sent by users.'],
            ]);
        }

        // Pesan yang dibalas harus ada di room yang sama. Tanpa ini, kutipan balasan
        // (MessageResource) bisa menampilkan isi pesan dari room privat/DM lain.
        if ($data->replyToId !== null && $data->replyToId !== '') {
            $parentInRoom = Message::where('id', $data->replyToId)
                ->where('room_id', $data->roomId)
                ->exists();

            if (! $parentInRoom) {
                throw ValidationException::withMessages([
                    'reply_to_id' => ['The message you are replying to was not found in this room.'],
                ]);
            }
        }

        // Format teks hanya diterima lewat input `entities` yang divalidasi —
        // tidak pernah langsung dari `metadata` kiriman klien.
        $metadata = $data->metadata;
        unset($metadata['entities'], $metadata['spoiler']);
        $entities = $this->entities->sanitize($data->body, $data->entities);
        if ($entities !== []) {
            $metadata['entities'] = $entities;
        }
        // Flag spoiler media juga hanya lewat input tervalidasi, bukan `metadata` klien.
        if ($data->spoiler && in_array($data->type, ['image', 'video'], true)) {
            $metadata['spoiler'] = true;
        }

        // Dicek terakhir, setelah semua validasi lain lolos, supaya request yang gagal tidak
        // menghabiskan jatah slow mode user.
        $this->enforceSlowMode($data, $member);

        $message = Message::create([
            'room_id' => $data->roomId,
            'user_id' => $data->userId,
            'reply_to_id' => $data->replyToId,
            'type' => $data->type,
            'body' => $data->body,
            'attachments' => $data->attachments,
            'metadata' => $metadata,
            'is_edited' => false,
            'is_deleted' => false,
        ]);

        event(new MessageSent($message));

        return $message;
    }

    /**
     * Terapkan `settings.slow_mode_seconds` room: satu pesan per user per jeda itu.
     * Owner room, moderator, dan admin dikecualikan. Memakai Cache::add (atomik) supaya
     * request paralel tidak bisa lolos bersamaan, dan menghapus pesan tidak mengosongkan jeda.
     */
    private function enforceSlowMode(SendMessageData $data, Member $member): void
    {
        $room = Room::find($data->roomId);
        $seconds = (int) ($room?->settings['slow_mode_seconds'] ?? 0);

        if ($seconds <= 0) {
            return;
        }

        if ((string) $room->owner_user_id === (string) $data->userId
            || in_array($member->role?->value, ['moderator', 'admin'], true)) {
            return;
        }

        $key = "discuss:slow-mode:{$data->roomId}:{$data->userId}";
        $now = now()->timestamp;

        if (! Cache::add($key, $now, $seconds)) {
            $wait = max(1, $seconds - ($now - (int) Cache::get($key, $now)));

            throw ValidationException::withMessages([
                'message' => ["Slow mode is on. Please wait {$wait} seconds before sending another message."],
            ]);
        }
    }

    /**
     * @param array{type: string, url: string, metadata?: array<string, mixed>}|null $attachment
     *        New attachment to put on the message (replaces the current one).
     * @param array<int, mixed> $entities
     *        Format teks untuk body baru. Entity lama selalu dibuang karena
     *        offset-nya hanya berlaku untuk body sebelumnya.
     * @param bool $spoiler
     *        Hanya dipakai bila `$attachment` mengganti media: menandai media
     *        baru sebagai spoiler. Tanpa media baru, flag lama dibiarkan.
     */
    public function edit(Message $message, ?string $newBody, string $byUserId, ?array $attachment = null, array $entities = [], bool $spoiler = false): Message
    {
        if ((string) $message->user_id !== (string) $byUserId) {
            throw ValidationException::withMessages([
                'message' => ['You are not allowed to edit this message.'],
            ]);
        }

        // Pesan yang sudah dihapus tidak boleh "dihidupkan" lagi lewat edit: edit akan
        // menyiarkan MessageEdited berisi isi baru dan mengubah baris yang sudah dihapus.
        if ($message->is_deleted) {
            throw ValidationException::withMessages([
                'message' => ['This message has been deleted.'],
            ]);
        }

        if (! in_array($message->type, [MessageType::Text, MessageType::Image, MessageType::File, MessageType::Video], true)) {
            throw ValidationException::withMessages([
                'message' => ['This message cannot be edited.'],
            ]);
        }

        $willHaveAttachment = $attachment !== null || ! empty($message->attachments);
        if (! $willHaveAttachment && trim((string) $newBody) === '') {
            throw ValidationException::withMessages([
                'body' => ['The body field is required.'],
            ]);
        }

        $metadata = $message->metadata ?? [];
        unset($metadata['entities']);
        $cleanEntities = $this->entities->sanitize($newBody, $entities);
        if ($cleanEntities !== []) {
            $metadata['entities'] = $cleanEntities;
        }

        $attributes = [
            'body' => $newBody,
            'is_edited' => true,
        ];

        if ($attachment !== null) {
            // Drop the previous file/video metadata, keep anything unrelated.
            unset($metadata['file'], $metadata['video'], $metadata['spoiler']);

            $attributes['type'] = $attachment['type'];
            $attributes['attachments'] = [$attachment['url']];
            $metadata = array_merge($metadata, $attachment['metadata'] ?? []);
            if ($spoiler && in_array($attachment['type'], ['image', 'video'], true)) {
                $metadata['spoiler'] = true;
            }
        }

        $attributes['metadata'] = $metadata;

        $message->update($attributes);

        event(new MessageEdited($message));

        return $message;
    }

    public function delete(Message $message, string $byUserId): void
    {
        $isOwner = (string) $message->user_id === (string) $byUserId;

        if (! $isOwner) {
            $actor = Member::where('room_id', $message->room_id)
                ->where('user_id', $byUserId)
                ->first();

            $isModerator = $actor
                && ! $actor->is_banned
                && in_array($actor->role->value, ['moderator', 'admin'], true);

            if (! $isModerator) {
                throw ValidationException::withMessages([
                    'message' => ['You are not allowed to delete this message.'],
                ]);
            }
        }

        $message->update([
            'is_deleted' => true,
            'deleted_by_user_id' => $byUserId,
        ]);

        event(new MessageDeleted($message));
    }

    /**
     * Pesan terhapus sengaja tetap ikut (sebagai penanda "dihapus" di timeline), tapi isinya
     * dikosongkan oleh MessageResource, jadi jangan kirim hasil ini ke klien tanpa resource.
     */
    public function paginate(string $roomId, int $limit = 50): Collection
    {
        return Message::where('room_id', $roomId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }
}
