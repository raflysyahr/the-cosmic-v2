<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;



Route::get('/', function () {
    return Inertia::render('Home');
});

Route::get('/series', function () {
    return Inertia::render('AllComics');
});

Route::get('/series/{slug}', function (string $slug) {
    return Inertia::render('Detail', ['slug' => $slug]);
});

Route::get('/series/{slug}/chapter/{chapterSlug}', function (string $slug, string $chapterSlug) {
    return Inertia::render('Reader', [
        'slug' => $slug,
        'chapterSlug' => $chapterSlug,
    ]);
});

Route::get('/comics/{genre}', function (string $genre) {
    return Inertia::render('GenrePage', ['genre' => $genre]);
});

Route::get('/search', function () {
    return Inertia::render('Search');
});

Route::get('/bookmarks', function () {
    return Inertia::render('Bookmarks');
});

Route::get('/about', function () {
    return Inertia::render('About');
});

Route::get('/privacy', function () {
    return Inertia::render('Privacy');
});

Route::get('/dmca', function () {
    return Inertia::render('DMCA');
});

Route::get('/cultivation-guide', function () {
    return Inertia::render('CultivationGuide');
});

Route::get('/contact', function () {
    return Inertia::render('Contact');
});

Route::get('/login', function (\App\Modules\Auth\Services\SocialAuthService $social) {
    return Inertia::render('Login', ['socialProviders' => $social->enabledProviders()]);
})->name('login');

Route::get('/register', function (\App\Modules\Auth\Services\SocialAuthService $social) {
    return Inertia::render('Register', ['socialProviders' => $social->enabledProviders()]);
});

Route::get('/500', function () {
    return Inertia::render('ServerError');
});

Route::get('/session-expired', function () {
    return Inertia::render('SessionExpired');
});

Route::get('/maintenance', function () {
    return Inertia::render('Maintenance');
});

Route::get('/403', function () {
    return Inertia::render('AccessDenied');
});

Route::fallback(function (\Illuminate\Http\Request $request) {
    return Inertia::render('NotFound')->toResponse($request)->setStatusCode(404);
});

use App\Modules\Discuss\Http\Controllers\PageController;
use App\Modules\Discuss\Http\Controllers\DirectController;
use App\Modules\Discuss\Http\Controllers\ToastDemoController;
use App\Http\Controllers\Api\BookmarkController;
use App\Http\Controllers\Api\ReadingHistoryController;
use App\Http\Controllers\Api\ChapterReactionController;

Route::middleware(['auth'])->prefix('discuss')->name('discuss.')->group(function () {
    Route::get('/', [PageController::class, 'index'])->name('index');
    // Harus di atas '/{slug}...' supaya tidak ditangkap sebagai slug room.
    Route::get('/story', [PageController::class, 'story'])->name('story');
    Route::get('/leaderboard', [PageController::class, 'leaderboard'])->name('leaderboard');
    Route::get('/leaderboard/admin', [PageController::class, 'cpAdmin'])->name('leaderboard.admin');
    Route::get('/invite/{token}', [PageController::class, 'joinByInvite'])->name('invite');
    Route::get('/{slug}/reports', [PageController::class, 'reports'])->name('reports');
    Route::get('/{slug}/about', [PageController::class, 'about'])->name('about');
    Route::get('/{slug}', [PageController::class, 'room'])->name('room');
});

// Demo toast (CP & Cultivation) — HANYA saat APP_ENV=local. Tidak ada di
// staging/production karena rutenya tidak didaftarkan sama sekali.
if (app()->environment('local')) {
    Route::middleware(['auth'])->get('/dev/toast-demo', [ToastDemoController::class, 'page'])->name('dev.toast-demo');
    Route::prefix('api')->middleware(['web', 'auth:sanctum'])
        ->post('/dev/toast-demo/push', [ToastDemoController::class, 'push']);
}

Route::middleware(['auth'])->prefix('direct')->name('direct.')->group(function () {
    Route::get('/', [DirectController::class, 'index'])->name('index');
    Route::post('/{username}', [DirectController::class, 'open'])->name('open');
});

Route::middleware('auth')->get('/profile', [\App\Modules\Auth\Http\Controllers\ProfileController::class, 'edit']);

Route::middleware('auth')->get('/u/{username}', [\App\Modules\Auth\Http\Controllers\ProfileController::class, 'showPublic'])->name('profile.public');

// Bookmarks & reading history are server-backed for logged-in users (see
// resources/js/hooks/useBookmarks.ts and useReadingHistory.ts, which fall
// back to localStorage for guests since user_id is required on both tables).
Route::prefix('api')->middleware(['web', 'auth:sanctum'])->group(function () {
    Route::get('/bookmarks', [BookmarkController::class, 'index']);
    Route::post('/bookmarks', [BookmarkController::class, 'store']);
    Route::delete('/bookmarks/{slug}', [BookmarkController::class, 'destroy']);

    Route::get('/reading-history/recent', [ReadingHistoryController::class, 'recent']);
    Route::get('/reading-history/{slug}', [ReadingHistoryController::class, 'index']);
    Route::post('/reading-history', [ReadingHistoryController::class, 'store']);

    // Reaksi halaman chapter (tabel `chapter_reactions`, terpisah dari reaksi pesan Discuss).
    Route::post('/series/{slug}/chapters/{index}/reactions', [ChapterReactionController::class, 'toggle'])
        ->whereNumber('index')
        ->middleware('throttle:discuss-write');
});

// Jumlah reaksi chapter boleh dilihat tamu; `web` saja (sesi opsional) agar user login tetap
// mendapat `my_reaction`.
Route::prefix('api')->middleware('web')->group(function () {
    Route::get('/series/{slug}/chapters/{index}/reactions', [ChapterReactionController::class, 'show'])
        ->whereNumber('index');
});

Route::get('/images/avatar-border.png', function () {
    return response()->file(storage_path('app/private/border/default-border.png'));
})->name('avatar.border');
