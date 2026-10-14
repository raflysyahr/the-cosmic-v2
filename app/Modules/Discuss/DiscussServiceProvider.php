<?php

namespace App\Modules\Discuss;

use App\Modules\Discuss\Console\GenerateStoryThumbnails;
use App\Modules\Discuss\Events\MessageDeleted;
use App\Modules\Discuss\Events\MessageSent;
use App\Modules\Discuss\Events\ReactionToggled;
use App\Modules\Discuss\Listeners\AwardCpOnReaction;
use App\Modules\Discuss\Listeners\AwardXpOnMessage;
use App\Modules\Discuss\Listeners\CheckRankPromotion;
use App\Modules\Discuss\Listeners\RevokeCpOnMessageDeleted;
use App\Modules\Discuss\Listeners\SendMentionNotification;
use App\Modules\Discuss\Listeners\SendReplyNotification;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class DiscussServiceProvider extends ServiceProvider
{
    /**
     * Sebelumnya kelas ini `extends ...Providers\AuthServiceProvider` dan
     * memakai `protected $listen`. Properti `$listen` hanya dibaca oleh
     * EventServiceProvider — bukan AuthServiceProvider — dan listener di
     * app/Modules/*\/Listeners tidak ikut auto-discovery Laravel (hanya
     * app/Listeners), jadi tidak ada satu pun listener Discuss yang terdaftar.
     * Pendaftaran eksplisit lewat Event::listen() di bawah yang benar-benar
     * jalan.
     */
    public function boot(): void
    {
        Event::listen(MessageSent::class, AwardXpOnMessage::class);
        Event::listen(MessageSent::class, CheckRankPromotion::class);
        Event::listen(MessageSent::class, SendMentionNotification::class);
        Event::listen(MessageSent::class, SendReplyNotification::class);
        Event::listen(ReactionToggled::class, AwardCpOnReaction::class);
        Event::listen(MessageDeleted::class, RevokeCpOnMessageDeleted::class);

        $this->loadRoutesFrom(__DIR__ . '/routes/discuss.php');
        $this->loadMigrationsFrom(__DIR__ . '/Database/Migrations');

        // WAJIB untuk broadcasting private/presence channel — tanpa ini,
        // endpoint POST /broadcasting/auth tidak pernah terdaftar, jadi
        // laravel-echo/pusher-js selalu gagal (404) saat otorisasi channel
        // apa pun, walau broadcastOn() dan Broadcast::channel() di
        // Channels/RoomChannel.php sudah benar. Middleware 'web' dipakai
        // (bukan 'auth:sanctum') karena project ini pakai Sanctum SPA
        // cookie-based (lihat resources/js/api/client.ts: withCredentials).
        Broadcast::routes(['middleware' => ['web']]);

        if ($this->app->runningInConsole()) {
            $this->commands([GenerateStoryThumbnails::class]);
        }

        require __DIR__ . '/Channels/RoomChannel.php';
    }
}
