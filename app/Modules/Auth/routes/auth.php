<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Auth\Http\Controllers\RegisterController;
use App\Modules\Auth\Http\Controllers\LoginController;
use App\Modules\Auth\Http\Controllers\LogoutController;
use App\Modules\Auth\Http\Controllers\ProfileController;
use App\Modules\Auth\Http\Controllers\EmailVerificationController;
use App\Modules\Auth\Http\Controllers\PasswordResetController;
use App\Modules\Auth\Http\Controllers\SocialAuthController;

Route::prefix('api')->middleware('web')->group(function () {
    // Guest routes
    Route::middleware('guest')->group(function () {
        Route::post('/register', [RegisterController::class, 'store']);
        Route::post('/login', [LoginController::class, 'store']);
        Route::post('/forgot-password', [PasswordResetController::class, 'sendLink']);
        Route::post('/reset-password', [PasswordResetController::class, 'reset']);

        Route::get('/auth/{provider}/redirect', [SocialAuthController::class, 'redirect']);
        Route::get('/auth/{provider}/callback', [SocialAuthController::class, 'callback']);
    });

    // Authenticated routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [LogoutController::class, 'destroy']);
        Route::get('/user', [ProfileController::class, 'show']);
        Route::match(['PUT', 'POST'], '/user/profile', [ProfileController::class, 'update']);

        Route::post('/email/verification-notification', [EmailVerificationController::class, 'send']);
    });

    // Email verification (signed)
    Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware(['auth:sanctum', 'signed'])
        ->name('verification.verify');
});
