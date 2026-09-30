<?php

namespace App\Modules\Auth\Data;

use App\Modules\Auth\Models\User;

class UserData
{
    public function __construct(
        public readonly string $id,
        public readonly string $username,
        public readonly string $email,
        public readonly string $displayName,
        public readonly ?string $avatarUrl,
        public readonly string $role,
        public readonly string $status,
    ) {}

    public static function fromModel(User $user): self
    {
        return new self(
            id: $user->id,
            username: $user->username,
            email: $user->email,
            displayName: $user->display_name,
            avatarUrl: $user->avatar_url,
            role: $user->role->value,
            status: $user->status->value,
        );
    }
}
