<?php

namespace App\Modules\Discuss\Events;

use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PinnedMessageUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $roomId,
        public readonly array $messageIds,
    ) {}

    public function broadcastOn(): PresenceChannel
    {
        return new PresenceChannel('room.' . $this->roomId);
    }

    public function broadcastWith(): array
    {
        return [
            'room_id' => $this->roomId,
            'message_ids' => $this->messageIds,
        ];
    }
}
