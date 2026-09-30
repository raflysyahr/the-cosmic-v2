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

Route::get('/login', function () {
    return Inertia::render('Login');
})->name('login');

Route::get('/register', function () {
    return Inertia::render('Register');
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

Route::fallback(function () {
    return Inertia::render('NotFound');
});

use App\Modules\Discuss\Http\Controllers\PageController;
use App\Modules\Discuss\Http\Controllers\DirectController;
use App\Http\Controllers\Api\BookmarkController;
use App\Http\Controllers\Api\ReadingHistoryController;

Route::middleware(['auth'])->prefix('discuss')->name('discuss.')->group(function () {
    Route::get('/', [PageController::class, 'index'])->name('index');
    Route::get('/invite/{token}', [PageController::class, 'joinByInvite'])->name('invite');
    Route::get('/{slug}/about', [PageController::class, 'about'])->name('about');
    Route::get('/{slug}', [PageController::class, 'room'])->name('room');
});

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
});

Route::get('/images/avatar-border.png', function () {
    return response()->file(storage_path('app/private/border/default-border.png'));
})->name('avatar.border');
