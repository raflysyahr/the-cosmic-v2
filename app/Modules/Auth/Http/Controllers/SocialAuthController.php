<?php

namespace App\Modules\Auth\Http\Controllers;

use App\Modules\Auth\Services\SocialAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;

class SocialAuthController
{
    public function __construct(
        private readonly SocialAuthService $socialAuthService,
    ) {}

    public function redirect(string $provider): JsonResponse
    {
        $url = Socialite::driver($provider)->stateless()->redirect()->getTargetUrl();

        return response()->json(['redirect_url' => $url]);
    }

    public function callback(string $provider): JsonResponse
    {
        $socialUser = Socialite::driver($provider)->stateless()->user();

        $user = $this->socialAuthService->handleCallback($provider, [
            'id' => $socialUser->getId(),
            'name' => $socialUser->getName(),
            'nickname' => $socialUser->getNickname(),
            'email' => $socialUser->getEmail(),
            'avatar' => $socialUser->getAvatar(),
            'token' => $socialUser->token,
            'refreshToken' => $socialUser->refreshToken,
            'expiresIn' => $socialUser->expiresIn,
        ]);

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful.',
            'token' => $token,
        ]);
    }
}
