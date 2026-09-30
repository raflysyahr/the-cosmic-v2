<?php

namespace App\Modules\Discuss\Events;

use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReactionToggled implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $roomId,
        public readonly string $messageId,
        public readonly string $userId,
        public readonly string $emoteId,
        public readonly string $action,
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
            'user_id' => $this->userId,
            'emote_id' => $this->emoteId,
            'action' => $this->action,
        ];
    }
}
