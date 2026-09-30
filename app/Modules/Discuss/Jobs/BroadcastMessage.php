<?php

namespace App\Modules\Discuss\Jobs;

use App\Modules\Discuss\Events\MessageSent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;

class BroadcastMessage implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function __construct(
        private readonly MessageSent $event,
    ) {}

    public function handle(): void
    {
        broadcast($this->event);
    }
}
