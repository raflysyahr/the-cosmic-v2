<?php

namespace App\Modules\Auth\Contracts;

use App\Modules\Auth\Data\RegisterData;
use App\Modules\Auth\Models\User;

interface AuthServiceContract
{
    public function register(RegisterData $data): User;

    public function login(string $email, string $password, bool $remember = false): User;

    public function logout(): void;
}
