<?php

namespace App\Modules\Discuss\Listeners;

use App\Modules\Discuss\Events\ReactionToggled;
use App\Modules\Discuss\Services\ContributionService;

class AwardCpOnReaction
{
    public function __construct(
        private readonly ContributionService $contribution,
    ) {}

    public function handle(ReactionToggled $event): void
    {
        // Hanya saat reaksi ditambahkan. Un-react tidak mencabut CP yang
        // sudah diberikan (dedupe per pesan+reaktor mencegah farming).
        if ($event->action !== 'added') {
            return;
        }

        $this->contribution->awardForReaction($event->roomId, $event->messageId, $event->userId);
    }
}
