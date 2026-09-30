<?php

namespace App\Modules\Auth\Models;

use App\Modules\Auth\Enums\UserRole;
use App\Modules\Auth\Enums\UserStatus;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasUlids, HasFactory, Notifiable, HasApiTokens;

    protected $fillable = [
        'username', 'email', 'password', 'display_name', 'avatar_url',
        'role', 'status', 'email_verified_at', 'last_login_at', 'preferences',
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'role' => UserRole::class,
            'status' => UserStatus::class,
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'preferences' => 'array',
            'password' => 'hashed',
        ];
    }

    public function getAvatarUrlAttribute(?string $value): string
    {
        return $value ?? 'https://ui-avatars.com/api/?name=' . urlencode($this->display_name);
    }

    // Tidak ada relationship cultivation() per AGENTS.md §2 (tidak ada
    // relasi lintas modul) — query manual ke UserCultivation di
    // CultivationService, lihat pola select() di sana.

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }
}
