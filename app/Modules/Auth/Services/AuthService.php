<?php

namespace App\Modules\Auth\Services;

use App\Modules\Auth\Contracts\AuthServiceContract;
use App\Modules\Auth\Data\RegisterData;
use App\Modules\Auth\Enums\UserStatus;
use App\Modules\Auth\Events\UserLoggedIn;
use App\Modules\Auth\Events\UserRegistered;
use App\Modules\Auth\Models\User;
use App\Modules\Auth\Models\UserProfile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService implements AuthServiceContract
{
    public function register(RegisterData $data): User
    {
        $user = User::create([
            'username' => $data->username,
            'email' => $data->email,
            'password' => Hash::make($data->password),
            'display_name' => $data->name,
            'role' => 'reader',
            'status' => 'active',
            'preferences' => [],
        ]);

        UserProfile::create([
            'user_id' => $user->id,
            'preferences' => [],
        ]);

        event(new UserRegistered($user));

        return $user;
    }

    public function login(string $email, string $password, bool $remember = false): User
    {
        $user = User::where('email', $email)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if ($user->status === UserStatus::Banned) {
            throw ValidationException::withMessages([
                'email' => ['Your account has been banned.'],
            ]);
        }

        event(new UserLoggedIn($user));

        return $user;
    }

    public function logout(): void
    {
        // Session logout handled by controller
    }
}
