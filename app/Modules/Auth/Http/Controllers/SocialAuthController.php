<?php

namespace App\Modules\Auth\Http\Controllers;

use App\Modules\Auth\Events\UserLoggedIn;
use App\Modules\Auth\Exceptions\SocialAuthException;
use App\Modules\Auth\Services\SocialAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Throwable;

/**
 * Login social memakai sesi cookie, sama persis dengan login email (LoginController):
 * auth()->login() + session()->regenerate(). Tidak ada token Bearer yang dikirim ke klien.
 * Parameter OAuth `state` disimpan di sesi dan diperiksa saat callback (tidak stateless).
 */
class SocialAuthController
{
    public function __construct(
        private readonly SocialAuthService $socialAuthService,
    ) {}

    public function redirect(string $provider): JsonResponse
    {
        $this->assertProviderEnabled($provider);

        $url = Socialite::driver($provider)->redirect()->getTargetUrl();

        return response()->json(['redirect_url' => $url]);
    }

    public function callback(Request $request, string $provider): RedirectResponse
    {
        $this->assertProviderEnabled($provider);

        try {
            $socialUser = Socialite::driver($provider)->user();

            $user = $this->socialAuthService->handleCallback($provider, [
                'id' => $socialUser->getId(),
                'name' => $socialUser->getName(),
                'nickname' => $socialUser->getNickname(),
                'email' => $socialUser->getEmail(),
                'email_verified' => $this->providerVerifiedEmail($provider, (array) ($socialUser->user ?? []), $socialUser->getEmail()),
                'avatar' => $socialUser->getAvatar(),
                'token' => $socialUser->token,
                'refreshToken' => $socialUser->refreshToken,
                'expiresIn' => $socialUser->expiresIn,
            ]);
        } catch (SocialAuthException $e) {
            return $this->failed($e->reason);
        } catch (InvalidStateException) {
            return $this->failed('invalid_state');
        } catch (Throwable $e) {
            report($e);

            return $this->failed('provider_error');
        }

        // remember = true: sama seperti login email dengan "Remember me" dicentang. Tanpa ini hanya
        // ada cookie sesi, jadi user logout otomatis saat sesi habis (SESSION_LIFETIME) / browser
        // ditutup. Halaman login social tidak punya checkbox "Remember me", jadi selalu diingat.
        auth()->login($user, true);
        $request->session()->regenerate();

        event(new UserLoggedIn($user));

        return redirect('/');
    }

    /**
     * Hanya provider yang benar-benar dikonfigurasi (services.<nama>.client_id) yang dilayani;
     * nama lain, termasuk yang tidak dikenal, mendapat 404 alih-alih error 500 dari Socialite.
     */
    private function assertProviderEnabled(string $provider): void
    {
        abort_unless(
            preg_match('/^[a-z0-9_-]+$/', $provider) === 1
                && filled(config("services.{$provider}.client_id")),
            404,
        );
    }

    /**
     * Apakah provider menyatakan email ini sudah terverifikasi. Provider yang tidak dikenal
     * dianggap tidak terverifikasi (aman: tidak akan ditautkan ke akun yang sudah ada).
     *
     * @param  array<string, mixed>  $raw  Payload mentah dari provider
     */
    private function providerVerifiedEmail(string $provider, array $raw, ?string $email): bool
    {
        if ($email === null || $email === '') {
            return false;
        }

        return match ($provider) {
            'google' => filter_var($raw['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'discord' => filter_var($raw['verified'] ?? false, FILTER_VALIDATE_BOOLEAN),
            // Socialite hanya mengembalikan email GitHub yang primary dan terverifikasi.
            'github' => true,
            // Facebook tidak memberi penanda email terverifikasi (field `verified` di payload-nya
            // berarti AKUN terverifikasi, bukan email). Selalu dianggap tidak terverifikasi:
            // tidak akan ditautkan ke akun yang sudah ada, dan akun baru diminta verifikasi email.
            'facebook' => false,
            default => false,
        };
    }

    private function failed(string $reason): RedirectResponse
    {
        return redirect('/login?social_error=' . urlencode($reason));
    }
}
