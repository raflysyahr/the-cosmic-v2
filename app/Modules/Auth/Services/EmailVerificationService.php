<?php

namespace App\Modules\Auth\Services;

use App\Modules\Auth\Models\User;
use App\Modules\Auth\Notifications\VerifyEmailNotification;
use Illuminate\Auth\Notifications\VerifyEmail;

class EmailVerificationService
{
    public function send(User $user): void
    {
        $user->notify(new VerifyEmail);
    }

    public function verify(User $user, string $hash): bool
    {
        if (! hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
            return false;
        }

        if ($user->hasVerifiedEmail()) {
            return true;
        }

        $user->markEmailAsVerified();

        return true;
    }
}
