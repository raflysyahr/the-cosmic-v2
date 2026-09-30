<?php

namespace App\Modules\Discuss\Listeners;

use App\Modules\Discuss\Events\MessageSent;
use App\Modules\Discuss\Services\ContributionService;

/**
 * Nama class dipertahankan (sudah dirujuk di PROJECT.md/MODULE_STRUCTURE.md),
 * tapi XP flat `room.settings.xp_per_message` diganti aturan Contribution
 * Points — lihat ContributionService. `xp_per_message = 0` di settings room
 * tetap berfungsi sebagai saklar untuk mematikan CP di room itu.
 */
class AwardXpOnMessage
{
    public function __construct(
        private readonly ContributionService $contribution,
    ) {}

    public function handle(MessageSent $event): void
    {
        $this->contribution->awardForMessage($event->message);
    }
}
