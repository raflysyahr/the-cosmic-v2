<?php

namespace App\Modules\Auth\Listeners;

use App\Modules\Auth\Events\UserLoggedIn;

class UpdateLastLogin
{
    public function handle(UserLoggedIn $event): void
    {
        $event->user->update([
            'last_login_at' => now(),
        ]);
    }
}
