<?php

namespace App\Modules\Auth\Listeners;

use App\Modules\Auth\Events\UserRegistered;
use App\Modules\Auth\Services\EmailVerificationService;
use Illuminate\Support\Str;
use Throwable;

class SendVerificationEmail
{
    public function __construct(
        private readonly EmailVerificationService $verificationService,
    ) {}

    public function handle(UserRegistered $event): void
    {
        $user = $event->user;

        // Akun social tanpa email asli diberi alamat placeholder "<id>@<provider>.oauth"
        // (lihat SocialAuthService); mengirim ke sana pasti gagal dan tidak berguna.
        if ($user->hasVerifiedEmail() || Str::endsWith((string) $user->email, '.oauth')) {
            return;
        }

        // Gagal kirim email (SMTP mati, dsb.) tidak boleh menggagalkan pendaftaran: akun sudah
        // terbuat, dan user bisa meminta kirim ulang lewat POST /api/email/verification-notification.
        try {
            $this->verificationService->send($user);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
