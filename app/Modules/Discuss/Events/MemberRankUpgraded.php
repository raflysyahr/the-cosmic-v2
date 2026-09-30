<?php

namespace App\Modules\Discuss\Events;

use App\Modules\Discuss\Models\Member;
use App\Modules\Discuss\Models\Rank;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MemberRankUpgraded implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Member $member,
        public readonly Rank $newRank,
        public readonly ?string $oldRankId,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        // PrivateChannel otomatis menambahkan prefix "private-" saat
        // broadcast ke Reverb/Pusher. Nama di sini (tanpa prefix manual)
        // harus cocok dengan pola authorization di
        // app/Modules/Discuss/Channels/RoomChannel.php: 'user.{userId}'.
        return new PrivateChannel('user.' . $this->member->user_id);
    }

    public function broadcastWith(): array
    {
        return [
            'user_id' => $this->member->user_id,
            'room_id' => $this->member->room_id,
            'rank_id' => $this->newRank->id,
            'rank_name' => $this->newRank->name,
            'rank_color' => $this->newRank->label_color,
        ];
    }
}
