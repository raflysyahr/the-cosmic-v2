<?php

namespace App\Modules\Discuss\Services;

use App\Modules\Discuss\Events\PinnedMessageUpdated;
use App\Modules\Discuss\Models\Message;
use App\Modules\Discuss\Models\Room;
use Illuminate\Validation\ValidationException;

class PinnedMessageService
{
    private const MAX_PINS = 5;

    public function __construct(
        private readonly MemberService $memberService,
    ) {}

    public function pin(Room $room, string $messageId, string $userId): array
    {
        $this->assertCanPin($room, $userId);

        $message = Message::where('id', $messageId)
            ->where('room_id', $room->id)
            ->where('is_deleted', false)
            ->first();

        if (! $message) {
            throw ValidationException::withMessages([
                'message' => ['Message not found in this room.'],
            ]);
        }

        $ids = $room->settings['pinned_message_ids'] ?? [];

        // Already pinned?
        if (in_array($message->id, $ids, true)) {
            return $this->getPinnedAll($room);
        }

        if (count($ids) >= self::MAX_PINS) {
            throw ValidationException::withMessages([
                'message' => ['Maximum ' . self::MAX_PINS . ' pinned messages per room.'],
            ]);
        }

        $ids[] = $message->id;

        $room->update([
            'settings' => array_merge($room->settings, [
                'pinned_message_ids' => $ids,
            ]),
        ]);

        event(new PinnedMessageUpdated($room->id, $ids));

        return $this->getPinnedAll($room);
    }

    public function unpin(Room $room, string $messageId, string $userId): array
    {
        $this->assertCanPin($room, $userId);

        $ids = array_values(array_filter(
            $room->settings['pinned_message_ids'] ?? [],
            fn ($id) => $id !== $messageId,
        ));

        $room->update([
            'settings' => array_merge($room->settings, [
                'pinned_message_ids' => $ids,
            ]),
        ]);

        event(new PinnedMessageUpdated($room->id, $ids));

        return $this->getPinnedAll($room);
    }

    public function getPinnedAll(Room $room): array
    {
        $ids = $room->settings['pinned_message_ids'] ?? [];

        if (empty($ids)) {
            return [];
        }

        $messages = Message::whereIn('id', $ids)
            ->where('room_id', $room->id)
            ->where('is_deleted', false)
            ->get();

        // Preserve pin order
        $result = [];
        foreach ($ids as $id) {
            $msg = $messages->firstWhere('id', $id);
            if (! $msg) {
                continue;
            }
            $user = \App\Modules\Auth\Models\User::select('id', 'display_name')
                ->where('id', $msg->user_id)
                ->first();
            $result[] = [
                'id' => $msg->id,
                'body' => $msg->body,
                'created_at' => $msg->created_at,
                'user' => $user ? [
                    'id' => $user->id,
                    'display_name' => $user->display_name,
                ] : null,
            ];
        }

        // Clean up stale IDs (deleted messages)
        if (count($result) !== count($ids)) {
            $cleanIds = array_column($result, 'id');
            $room->update([
                'settings' => array_merge($room->settings, [
                    'pinned_message_ids' => $cleanIds,
                ]),
            ]);
        }

        return $result;
    }

    private function assertCanPin(Room $room, string $userId): void
    {
        if ($room->context_type === 'direct') {
            throw ValidationException::withMessages([
                'room' => ['Cannot pin messages in direct chats.'],
            ]);
        }

        $role = $this->memberService->roleOf($room->id, $userId);

        if ($role !== 'admin') {
            throw ValidationException::withMessages([
                'room' => ['Only admins can pin messages.'],
            ]);
        }
    }
}
