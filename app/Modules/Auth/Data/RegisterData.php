<?php

namespace App\Modules\Auth\Data;

class RegisterData
{
    public function __construct(
        public readonly string $name,
        public readonly string $username,
        public readonly string $email,
        public readonly string $password,
    ) {}
}
