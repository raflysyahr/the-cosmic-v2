<?php

namespace App\Modules\Auth\Services;

use App\Modules\Auth\Models\User;
use App\Modules\Auth\Models\UserProfile;
use App\Modules\Auth\Models\UserSocialAccount;
use App\Modules\Auth\Events\UserRegistered;
use Illuminate\Support\Str;

class SocialAuthService
{
    public function handleCallback(string $provider, array $socialUser): User
    {
        $account = UserSocialAccount::where('provider', $provider)
            ->where('provider_id', $socialUser['id'])
            ->first();

        if ($account) {
            return User::findOrFail($account->user_id);
        }

        $email = $socialUser['email'] ?? null;
        $user = null;

        if ($email) {
            $user = User::where('email', $email)->first();
        }

        if (! $user) {
            $user = User::create([
                'username' => Str::slug($socialUser['name'] ?? $socialUser['nickname'] ?? 'user') . '-' . Str::random(4),
                'email' => $email ?? ($socialUser['id'] . '@' . $provider . '.oauth'),
                'password' => null,
                'display_name' => $socialUser['name'] ?? $socialUser['nickname'] ?? 'User',
                'avatar_url' => $socialUser['avatar'] ?? null,
                'role' => 'reader',
                'status' => 'active',
                'preferences' => [],
            ]);

            UserProfile::create([
                'user_id' => $user->id,
                'preferences' => [],
            ]);

            event(new UserRegistered($user));
        }

        UserSocialAccount::create([
            'user_id' => $user->id,
            'provider' => $provider,
            'provider_id' => $socialUser['id'],
            'access_token' => $socialUser['token'] ?? '',
            'refresh_token' => $socialUser['refreshToken'] ?? null,
            'token_expires_at' => isset($socialUser['expiresIn'])
                ? now()->addSeconds($socialUser['expiresIn'])
                : null,
        ]);

        return $user;
    }
}
