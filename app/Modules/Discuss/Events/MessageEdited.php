<?php

namespace App\Modules\Discuss\Events;

use App\Modules\Discuss\Models\Message;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageEdited implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Message $message,
    ) {}

    public function broadcastOn(): PresenceChannel
    {
        return new PresenceChannel('room.' . $this->message->room_id);
    }

    public function broadcastWith(): array
    {
        // Field ini bernama `message_id` (bukan `id`) supaya cocok dengan
        // yang diharapkan listener frontend: Room.tsx channel.listen
        // ('MessageEdited', (e: { message_id: string; body: string }) =>
        // ...). Sebelumnya bernama `id`, jadi e.message_id selalu
        // undefined dan pesan yang di-edit tidak pernah ter-update di
        // sisi client lain.
        return [
            'message_id' => $this->message->id,
            'body' => $this->message->body,
            'type' => $this->message->type->value,
            'attachments' => $this->message->attachments,
            'metadata' => $this->message->metadata,
            'is_edited' => true,
            'updated_at' => $this->message->updated_at,
        ];
    }
}
