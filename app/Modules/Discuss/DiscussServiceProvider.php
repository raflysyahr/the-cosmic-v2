<?php

namespace App\Modules\Discuss;

use App\Modules\Discuss\Events\MessageSent;
use App\Modules\Discuss\Listeners\AwardXpOnMessage;
use App\Modules\Discuss\Listeners\CheckRankPromotion;
use App\Modules\Discuss\Listeners\SendMentionNotification;
use App\Modules\Discuss\Listeners\SendReplyNotification;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Broadcast;

class DiscussServiceProvider extends ServiceProvider
{
    protected $listen = [
        MessageSent::class => [
            AwardXpOnMessage::class,
            CheckRankPromotion::class,
            SendMentionNotification::class,
            SendReplyNotification::class,
        ],
    ];

    public function boot(): void
    {
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

        require __DIR__ . '/Channels/RoomChannel.php';
    }
}
