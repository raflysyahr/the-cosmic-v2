<?php

namespace App\Modules\Auth;

use App\Modules\Auth\Events\UserLoggedIn;
use App\Modules\Auth\Events\UserRegistered;
use App\Modules\Auth\Listeners\SendVerificationEmail;
use App\Modules\Auth\Listeners\UpdateLastLogin;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $listen = [
        UserRegistered::class => [
            SendVerificationEmail::class,
        ],
        UserLoggedIn::class => [
            UpdateLastLogin::class,
        ],
    ];

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/routes/auth.php');
        $this->loadMigrationsFrom(__DIR__ . '/Database/Migrations');
    }
}
