<?php

namespace App\Modules\Discuss\Listeners;

use App\Modules\Discuss\Events\MessageSent;
use App\Modules\Discuss\Models\Member;
use App\Modules\Discuss\Services\RankService;

class CheckRankPromotion
{
    public function __construct(
        private readonly RankService $rankService,
    ) {}

    public function handle(MessageSent $event): void
    {
        $member = Member::where('room_id', $event->message->room_id)
            ->where('user_id', $event->message->user_id)
            ->first();

        if (! $member) {
            return;
        }

        $this->rankService->checkPromotion($member);
    }
}
