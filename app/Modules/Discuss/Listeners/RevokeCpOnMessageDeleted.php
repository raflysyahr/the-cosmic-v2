<?php

namespace App\Modules\Discuss\Listeners;

use App\Modules\Discuss\Events\MessageDeleted;
use App\Modules\Discuss\Services\ContributionService;

class RevokeCpOnMessageDeleted
{
    public function __construct(
        private readonly ContributionService $contribution,
    ) {}

    public function handle(MessageDeleted $event): void
    {
        $this->contribution->revokeForMessage($event->message);
    }
}
