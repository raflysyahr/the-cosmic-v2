<?php

namespace App\Modules\Auth\Services;

use App\Modules\Auth\Models\User;
use App\Modules\Auth\Notifications\PasswordResetNotification;
use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Support\Facades\Password;

class PasswordResetService
{
    public function sendLink(string $email): void
    {
        $user = User::where('email', $email)->first();

        if (! $user) {
            return;
        }

        $token = Password::broker()->createToken($user);

        $user->notify(new PasswordResetNotification($token));
    }

    public function reset(string $email, string $token, string $password): bool
    {
        $user = User::where('email', $email)->first();

        if (! $user) {
            return false;
        }

        $broker = Password::broker();

        $result = $broker->reset(
            ['email' => $email, 'token' => $token, 'password' => $password],
            function ($user, $password) {
                $user->password = bcrypt($password);
                $user->save();
            }
        );

        return $result === Password::PASSWORD_RESET;
    }
}
