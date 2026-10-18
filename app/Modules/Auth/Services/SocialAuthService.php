<?php

namespace App\Modules\Auth\Services;

use App\Modules\Auth\Enums\UserStatus;
use App\Modules\Auth\Events\UserRegistered;
use App\Modules\Auth\Exceptions\SocialAuthException;
use App\Modules\Auth\Models\User;
use App\Modules\Auth\Models\UserProfile;
use App\Modules\Auth\Models\UserSocialAccount;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SocialAuthService
{
    /**
     * Cari atau buat user dari identitas provider.
     *
     * Aturan penautan ke akun yang sudah ada: email provider harus terverifikasi DAN akun
     * lokalnya juga sudah terverifikasi. Tanpa itu, siapa pun yang bisa membuat akun di
     * provider dengan email korban (atau mendaftar lokal lebih dulu dengan email korban)
     * bisa mengambil alih akun. Kalau salah satu belum terbukti, ditolak dan user diminta
     * masuk dengan password / reset password dulu.
     *
     * @param  array{id: string|int, name?: ?string, nickname?: ?string, email?: ?string, email_verified?: bool, avatar?: ?string, token?: ?string, refreshToken?: ?string, expiresIn?: ?int}  $socialUser
     *
     * @throws SocialAuthException
     */
    public function handleCallback(string $provider, array $socialUser): User
    {
        $existing = $this->findByProviderAccount($provider, (string) $socialUser['id']);

        if ($existing) {
            $this->assertCanLogin($existing);

            return $existing;
        }

        $email = $socialUser['email'] ?? null;
        $emailVerified = $email !== null && ($socialUser['email_verified'] ?? false) === true;
        $local = $email ? User::where('email', $email)->first() : null;

        if ($local && (! $emailVerified || ! $local->hasVerifiedEmail())) {
            throw new SocialAuthException(SocialAuthException::EMAIL_IN_USE);
        }

        if ($local) {
            $this->assertCanLogin($local);
        }

        $created = false;

        try {
            $user = DB::transaction(function () use ($provider, $socialUser, $email, $emailVerified, $local, &$created) {
                $user = $local;

                if (! $user) {
                    $user = $this->createUser($provider, $socialUser, $email, $emailVerified);
                    $created = true;
                }

                UserSocialAccount::create([
                    'user_id' => $user->id,
                    'provider' => $provider,
                    'provider_id' => (string) $socialUser['id'],
                    'access_token' => $socialUser['token'] ?? '',
                    'refresh_token' => $socialUser['refreshToken'] ?? null,
                    'token_expires_at' => isset($socialUser['expiresIn'])
                        ? now()->addSeconds($socialUser['expiresIn'])
                        : null,
                ]);

                return $user;
            });
        } catch (UniqueConstraintViolationException $e) {
            // Dua callback paralel untuk identitas provider yang sama: yang kalah memakai hasil yang menang.
            $user = $this->findByProviderAccount($provider, (string) $socialUser['id']);

            if (! $user) {
                throw $e;
            }

            $this->assertCanLogin($user);

            return $user;
        }

        if ($created) {
            event(new UserRegistered($user));
        }

        return $user;
    }

    private function findByProviderAccount(string $provider, string $providerId): ?User
    {
        $account = UserSocialAccount::where('provider', $provider)
            ->where('provider_id', $providerId)
            ->first();

        return $account ? User::findOrFail($account->user_id) : null;
    }

    /**
     * Sama dengan login email: akun yang di-ban tidak boleh masuk lewat jalur mana pun.
     */
    private function assertCanLogin(User $user): void
    {
        if ($user->status === UserStatus::Banned) {
            throw new SocialAuthException(SocialAuthException::BANNED);
        }
    }

    private function createUser(string $provider, array $socialUser, ?string $email, bool $emailVerified): User
    {
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

        // Email yang sudah dibuktikan provider tidak perlu diverifikasi ulang.
        if ($emailVerified) {
            $user->markEmailAsVerified();
        }

        UserProfile::create([
            'user_id' => $user->id,
            'preferences' => [],
        ]);

        return $user;
    }
}
