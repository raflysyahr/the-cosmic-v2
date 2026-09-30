<?php

namespace App\Modules\Discuss\Events;

use App\Modules\Auth\Models\User;
use App\Modules\Discuss\Models\Member;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MemberBanned implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Member $member,
    ) {}

    public function broadcastOn(): PresenceChannel
    {
        return new PresenceChannel('room.' . $this->member->room_id);
    }

    public function broadcastWith(): array
    {
        $user = User::select('display_name')->where('id', $this->member->user_id)->first();

        return [
            'user_id' => $this->member->user_id,
            'room_id' => $this->member->room_id,
            'display_name' => $user?->display_name ?? 'Someone',
        ];
    }
}
