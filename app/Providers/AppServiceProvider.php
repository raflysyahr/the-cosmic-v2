<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        $this->configureRateLimiting();
    }

    /**
     * Batas permintaan per endpoint sensitif. Dipasang di route lewat `throttle:<nama>`.
     * Penting: di belakang proxy/load balancer, konfigurasikan trusted proxies supaya
     * `$request->ip()` adalah IP klien, bukan IP proxy (kalau tidak, semua user berbagi satu kuota).
     */
    private function configureRateLimiting(): void
    {
        $email = fn (Request $r): string => mb_strtolower(trim((string) $r->input('email')));
        $actor = fn (Request $r): string => (string) ($r->user()?->id ?? $r->ip());

        // ── Auth ──
        // Per email+IP menahan brute force ke satu akun; per IP menahan pergantian email.
        RateLimiter::for('auth-login', fn (Request $r) => [
            Limit::perMinute(5)->by('login|' . $email($r) . '|' . $r->ip()),
            Limit::perMinute(20)->by('login-ip|' . $r->ip()),
        ]);

        RateLimiter::for('auth-register', fn (Request $r) => [
            Limit::perHour(5)->by('register-ip|' . $r->ip()),
        ]);

        // Tiap permintaan mengirim email: batasi per alamat tujuan dan per IP.
        RateLimiter::for('auth-forgot', fn (Request $r) => [
            Limit::perHour(3)->by('forgot|' . $email($r)),
            Limit::perHour(10)->by('forgot-ip|' . $r->ip()),
        ]);

        RateLimiter::for('auth-reset', fn (Request $r) => [
            Limit::perMinute(5)->by('reset|' . $email($r) . '|' . $r->ip()),
            Limit::perMinute(20)->by('reset-ip|' . $r->ip()),
        ]);

        RateLimiter::for('auth-social', fn (Request $r) => [
            Limit::perMinute(20)->by('social-ip|' . $r->ip()),
        ]);

        // ── Discuss ──
        // Kirim/edit pesan. Request yang membawa file dihitung juga di batas upload yang lebih
        // ketat, supaya satu user tidak bisa memenuhi disk dengan upload berulang.
        RateLimiter::for('discuss-send', function (Request $r) use ($actor) {
            $limits = [Limit::perMinute(20)->by('msg|' . $actor($r))];

            if ($r->allFiles() !== []) {
                $limits[] = Limit::perMinute(10)->by('upload-min|' . $actor($r));
                $limits[] = Limit::perHour(100)->by('upload-hour|' . $actor($r));
            }

            return $limits;
        });

        // Aksi tulis lain: buat/ubah room, join/leave, moderasi member, reaksi, mark, pin.
        RateLimiter::for('discuss-write', fn (Request $r) => [
            Limit::perMinute(30)->by('write|' . $actor($r)),
        ]);

        // Laporan lebih ketat supaya antrean moderasi tidak dibanjiri.
        RateLimiter::for('discuss-report', fn (Request $r) => [
            Limit::perMinute(10)->by('report|' . $actor($r)),
        ]);
    }
}
