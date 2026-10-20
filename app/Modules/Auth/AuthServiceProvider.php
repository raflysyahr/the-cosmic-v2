<?php

namespace App\Modules\Auth;

use App\Modules\Auth\Events\UserLoggedIn;
use App\Modules\Auth\Events\UserRegistered;
use App\Modules\Auth\Listeners\SendVerificationEmail;
use App\Modules\Auth\Listeners\UpdateLastLogin;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * Sebelumnya kelas ini `extends Illuminate\Foundation\Support\Providers\AuthServiceProvider`
     * dan memakai `protected $listen`. Properti `$listen` hanya dibaca oleh EventServiceProvider,
     * bukan AuthServiceProvider, dan listener di app/Modules/*\/Listeners tidak ikut
     * auto-discovery Laravel, jadi tidak ada listener Auth yang terdaftar: email verifikasi
     * tidak terkirim dan `last_login_at` tidak pernah diperbarui. Pendaftaran eksplisit lewat
     * Event::listen() di bawah yang benar-benar jalan (pola yang sama dengan DiscussServiceProvider).
     */
    public function boot(): void
    {
        Event::listen(UserRegistered::class, SendVerificationEmail::class);
        Event::listen(UserLoggedIn::class, UpdateLastLogin::class);

        $this->loadRoutesFrom(__DIR__ . '/routes/auth.php');
        $this->loadMigrationsFrom(__DIR__ . '/Database/Migrations');
    }
}
