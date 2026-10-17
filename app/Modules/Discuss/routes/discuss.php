<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Discuss\Http\Controllers\RoomController;
use App\Modules\Discuss\Http\Controllers\MemberController;
use App\Modules\Discuss\Http\Controllers\MessageController;
use App\Modules\Discuss\Http\Controllers\ReactionController;
use App\Modules\Discuss\Http\Controllers\EmoteController;
use App\Modules\Discuss\Http\Controllers\RankController;
use App\Modules\Discuss\Http\Controllers\PinnedMessageController;
use App\Modules\Discuss\Http\Controllers\NotificationController;
use App\Modules\Discuss\Http\Controllers\CpAdminController;
use App\Modules\Discuss\Http\Controllers\MarkController;
use App\Modules\Discuss\Http\Controllers\ReportController;
use App\Modules\Discuss\Http\Controllers\AchievementController;
use App\Modules\Discuss\Http\Controllers\AnnouncementController;
use App\Modules\Discuss\Http\Controllers\LeaderboardController;

Route::prefix('api')->middleware(['web', 'auth:sanctum'])->group(function () {
    // Rooms
    Route::get('/rooms', [RoomController::class, 'index']);
    Route::post('/rooms', [RoomController::class, 'store'])->middleware('throttle:discuss-write');
    Route::get('/rooms/{slug}', [RoomController::class, 'show']);
    Route::put('/rooms/{slug}', [RoomController::class, 'update'])->middleware('throttle:discuss-write');
    Route::delete('/rooms/{slug}', [RoomController::class, 'destroy'])->middleware('throttle:discuss-write');
    Route::post('/rooms/{slug}/invite', [RoomController::class, 'generateInvite'])->middleware('throttle:discuss-write');

    // Members
    Route::get('/rooms/{slug}/members', [MemberController::class, 'index']);
    Route::post('/rooms/{slug}/join', [MemberController::class, 'store'])->middleware('throttle:discuss-write');
    Route::delete('/rooms/{slug}/leave', [MemberController::class, 'destroy'])->middleware('throttle:discuss-write');
    Route::post('/rooms/{slug}/members/{userId}/kick', [MemberController::class, 'kick'])->middleware('throttle:discuss-write');
    Route::post('/rooms/{slug}/members/{userId}/mute', [MemberController::class, 'mute'])->middleware('throttle:discuss-write');
    Route::post('/rooms/{slug}/members/{userId}/ban', [MemberController::class, 'ban'])->middleware('throttle:discuss-write');

    // Messages
    Route::get('/rooms/{slug}/messages', [MessageController::class, 'index']);
    Route::post('/rooms/{slug}/messages', [MessageController::class, 'store'])->middleware('throttle:discuss-send');
    Route::put('/rooms/{slug}/messages/{messageId}', [MessageController::class, 'update'])->middleware('throttle:discuss-send');
    Route::delete('/rooms/{slug}/messages/{messageId}', [MessageController::class, 'destroy'])->middleware('throttle:discuss-write');

    // Reactions
    Route::post('/rooms/{slug}/messages/{messageId}/reactions', [ReactionController::class, 'store'])->middleware('throttle:discuss-write');

    // Helpful & Best Answer (toggle)
    Route::post('/rooms/{slug}/messages/{messageId}/helpful', [MarkController::class, 'helpful'])->middleware('throttle:discuss-write');
    Route::post('/rooms/{slug}/messages/{messageId}/best-answer', [MarkController::class, 'bestAnswer'])->middleware('throttle:discuss-write');

    // Story = post dari admin (teks/foto/video + caption); user membaca & memberi reaksi
    Route::get('/discuss/story', [AnnouncementController::class, 'index']);
    Route::get('/discuss/story/unread', [AnnouncementController::class, 'unread']);
    Route::post('/discuss/story/seen', [AnnouncementController::class, 'seen']);
    Route::post('/discuss/story/{id}/reaction', [AnnouncementController::class, 'react']);
    Route::get('/admin/story', [AnnouncementController::class, 'adminIndex']);
    Route::post('/admin/story', [AnnouncementController::class, 'store']);
    Route::put('/admin/story/{id}', [AnnouncementController::class, 'update']);
    Route::delete('/admin/story/{id}', [AnnouncementController::class, 'destroy']);

    // Reports (moderasi) & achievements
    Route::post('/rooms/{slug}/messages/{messageId}/report', [ReportController::class, 'store'])->middleware('throttle:discuss-report');
    Route::get('/rooms/{slug}/reports', [ReportController::class, 'index']);
    Route::post('/rooms/{slug}/reports/{reportId}/resolve', [ReportController::class, 'resolve']);
    Route::get('/discuss/achievements', [AchievementController::class, 'index']);

    // Pinned Messages
    Route::post('/rooms/{slug}/pin', [PinnedMessageController::class, 'store'])->middleware('throttle:discuss-write');
    Route::delete('/rooms/{slug}/pin', [PinnedMessageController::class, 'destroy'])->middleware('throttle:discuss-write');

    // Emotes
    Route::get('/emotes', [EmoteController::class, 'index']);
    Route::post('/emotes', [EmoteController::class, 'store']);
    Route::delete('/emotes/{emoteId}', [EmoteController::class, 'destroy']);

    // Ranks
    Route::get('/rooms/{slug}/ranks', [RankController::class, 'index']);
    Route::post('/rooms/{slug}/ranks', [RankController::class, 'store']);
    Route::put('/rooms/{slug}/ranks/{rankId}', [RankController::class, 'update']);
    Route::delete('/rooms/{slug}/ranks/{rankId}', [RankController::class, 'destroy']);

    // Contribution Points (CP): leaderboard, event aktif, dan pengaturan admin
    Route::get('/discuss/leaderboard', [LeaderboardController::class, 'global']);
    Route::get('/rooms/{slug}/leaderboard', [LeaderboardController::class, 'room']);
    Route::get('/cp/active-events', [CpAdminController::class, 'activeEvents']);
    Route::get('/admin/cp/settings', [CpAdminController::class, 'showSettings']);
    Route::put('/admin/cp/settings', [CpAdminController::class, 'updateSettings']);
    Route::get('/admin/cp/events', [CpAdminController::class, 'listEvents']);
    Route::post('/admin/cp/events', [CpAdminController::class, 'storeEvent']);
    Route::put('/admin/cp/events/{eventId}', [CpAdminController::class, 'updateEvent']);
    Route::delete('/admin/cp/events/{eventId}', [CpAdminController::class, 'destroyEvent']);

    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);
    Route::put('/notifications/{id}', [NotificationController::class, 'update']);
});
