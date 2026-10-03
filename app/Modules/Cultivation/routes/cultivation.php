<?php

use App\Modules\Cultivation\Http\Controllers\CultivationController;
use App\Modules\Cultivation\Http\Controllers\RealmBadgeController;
use Illuminate\Support\Facades\Route;

Route::prefix('api')->middleware(['web', 'auth:sanctum'])->group(function () {
    Route::get('/cultivation', [CultivationController::class, 'show']);
    Route::post('/cultivation/chapter-complete', [CultivationController::class, 'completeChapter']);


});

// Badge realm & data panduan — publik, tidak butuh auth (pola sama dengan
// GET /api/v1/series/{slug}/cover di modul legacy, lihat routes/api.php).
Route::prefix('api')->group(function () {
    Route::get('/cultivation/realm-badge/{slug}', RealmBadgeController::class);
    Route::get('/cultivation/guide', [CultivationController::class, 'guide']);
});
