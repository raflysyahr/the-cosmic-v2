<?php

namespace App\Modules\Discuss\Services;

use App\Modules\Discuss\Contracts\RankServiceContract;
use App\Modules\Discuss\Events\MemberRankUpgraded;
use App\Modules\Discuss\Models\Member;
use App\Modules\Discuss\Models\Rank;

class RankService implements RankServiceContract
{
    public function awardXp(Member $member, int $points): void
    {
        $member->increment('xp_points', $points);
    }

    public function checkPromotion(Member $member): void
    {
        $eligibleRank = Rank::forRoom($member->room_id)
            ->where('min_xp', '<=', $member->xp_points)
            ->orderBy('order', 'desc')
            ->first();

        if (! $eligibleRank) {
            return;
        }

        if ((string) $member->rank_id === (string) $eligibleRank->id) {
            return;
        }

        $oldRankId = $member->rank_id;
        $member->update(['rank_id' => $eligibleRank->id]);

        event(new MemberRankUpgraded(
            $member,
            $eligibleRank,
            $oldRankId,
        ));
    }

    public function ranksForRoom(?string $roomId): iterable
    {
        return Rank::forRoom($roomId)->orderBy('order')->get();
    }
}
