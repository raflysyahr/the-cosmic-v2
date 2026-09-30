<?php

namespace App\Modules\Discuss\Contracts;

use App\Modules\Discuss\Models\Member;

interface RankServiceContract
{
    public function awardXp(Member $member, int $points): void;

    public function checkPromotion(Member $member): void;

    public function ranksForRoom(?string $roomId): iterable;
}
