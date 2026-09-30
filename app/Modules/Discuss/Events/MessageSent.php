<?php

namespace App\Modules\Discuss\Events;

use App\Modules\Discuss\Http\Resources\MessageResource;
use App\Modules\Discuss\Models\Message;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcast
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
        // Pakai MessageResource yang sama dengan response HTTP
        // (MessageController::store) — supaya payload broadcast dan
        // payload HTTP selalu identik (user object lengkap, reactions,
        // reply_to, dst). Sebelumnya array ini ditulis manual dan cuma
        // kirim user_id (bukan objek user), yang bikin frontend
        // (interface Message di MessageItem.tsx, field `user` wajib)
        // menerima data tidak lengkap saat pesan masuk lewat broadcast.
        return [
            'message' => (new MessageResource($this->message))->resolve(),
        ];
    }
}
