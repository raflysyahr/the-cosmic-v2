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

Route::prefix('api')->middleware(['web', 'auth:sanctum'])->group(function () {
    // Rooms
    Route::get('/rooms', [RoomController::class, 'index']);
    Route::post('/rooms', [RoomController::class, 'store']);
    Route::get('/rooms/{slug}', [RoomController::class, 'show']);
    Route::put('/rooms/{slug}', [RoomController::class, 'update']);
    Route::delete('/rooms/{slug}', [RoomController::class, 'destroy']);
    Route::post('/rooms/{slug}/invite', [RoomController::class, 'generateInvite']);

    // Members
    Route::get('/rooms/{slug}/members', [MemberController::class, 'index']);
    Route::post('/rooms/{slug}/join', [MemberController::class, 'store']);
    Route::delete('/rooms/{slug}/leave', [MemberController::class, 'destroy']);
    Route::post('/rooms/{slug}/members/{userId}/kick', [MemberController::class, 'kick']);
    Route::post('/rooms/{slug}/members/{userId}/mute', [MemberController::class, 'mute']);
    Route::post('/rooms/{slug}/members/{userId}/ban', [MemberController::class, 'ban']);

    // Messages
    Route::get('/rooms/{slug}/messages', [MessageController::class, 'index']);
    Route::post('/rooms/{slug}/messages', [MessageController::class, 'store']);
    Route::put('/rooms/{slug}/messages/{messageId}', [MessageController::class, 'update']);
    Route::delete('/rooms/{slug}/messages/{messageId}', [MessageController::class, 'destroy']);

    // Reactions
    Route::post('/rooms/{slug}/messages/{messageId}/reactions', [ReactionController::class, 'store']);

    // Pinned Messages
    Route::post('/rooms/{slug}/pin', [PinnedMessageController::class, 'store']);
    Route::delete('/rooms/{slug}/pin', [PinnedMessageController::class, 'destroy']);

    // Emotes
    Route::get('/emotes', [EmoteController::class, 'index']);
    Route::post('/emotes', [EmoteController::class, 'store']);
    Route::delete('/emotes/{emoteId}', [EmoteController::class, 'destroy']);

    // Ranks
    Route::get('/rooms/{slug}/ranks', [RankController::class, 'index']);
    Route::post('/rooms/{slug}/ranks', [RankController::class, 'store']);
    Route::put('/rooms/{slug}/ranks/{rankId}', [RankController::class, 'update']);
    Route::delete('/rooms/{slug}/ranks/{rankId}', [RankController::class, 'destroy']);

    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);
    Route::put('/notifications/{id}', [NotificationController::class, 'update']);
});
