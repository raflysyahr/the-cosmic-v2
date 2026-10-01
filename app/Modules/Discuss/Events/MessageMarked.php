<?php

namespace App\Modules\Discuss\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Helpful / Best Answer ditambah atau dicabut. Payload membawa STATE
 * LENGKAP (bukan delta) supaya klien cukup menimpa `marks` pesan itu —
 * aman kalau event terkirim dua kali atau datang tidak berurutan.
 */
class MessageMarked implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /** @param list<string> $helpfulUserIds */
    public function __construct(
        public readonly string $roomId,
        public readonly string $messageId,
        public readonly string $kind,
        public readonly string $action,
        public readonly array $helpfulUserIds,
        public readonly bool $isBestAnswer,
    ) {}

    public function broadcastOn(): PresenceChannel
    {
        return new PresenceChannel('room.' . $this->roomId);
    }

    public function broadcastWith(): array
    {
        return [
            'room_id' => $this->roomId,
            'message_id' => $this->messageId,
            'kind' => $this->kind,
            'action' => $this->action,
            'marks' => [
                'helpful_count' => count($this->helpfulUserIds),
                'helpful_user_ids' => $this->helpfulUserIds,
                'is_best_answer' => $this->isBestAnswer,
            ],
        ];
    }
}
