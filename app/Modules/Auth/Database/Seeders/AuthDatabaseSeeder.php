<?php

namespace App\Modules\Auth\Database\Seeders;

use App\Modules\Auth\Models\User;
use Illuminate\Database\Seeder;

class AuthDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'username' => 'admin',
            'email' => 'admin@thecosmic.com',
            'password' => 'password',
            'display_name' => 'Admin',
            'role' => 'admin',
            'status' => 'active',
            'email_verified_at' => now(),
            'preferences' => [],
        ]);
    }
}
