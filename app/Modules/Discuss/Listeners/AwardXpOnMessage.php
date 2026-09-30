<?php

namespace App\Modules\Discuss\Listeners;

use App\Modules\Discuss\Events\MessageSent;
use App\Modules\Discuss\Models\Member;
use App\Modules\Discuss\Models\Room;
use App\Modules\Discuss\Services\RankService;

class AwardXpOnMessage
{
    public function __construct(
        private readonly RankService $rankService,
    ) {}

    public function handle(MessageSent $event): void
    {
        $room = Room::find($event->message->room_id);

        if (! $room) {
            return;
        }

        $xpPerMessage = $room->settings['xp_per_message'] ?? 5;

        $member = Member::where('room_id', $event->message->room_id)
            ->where('user_id', $event->message->user_id)
            ->first();

        if (! $member) {
            return;
        }

        $this->rankService->awardXp($member, $xpPerMessage);
    }
}
