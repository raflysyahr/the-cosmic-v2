<?php

namespace App\Modules\Auth\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class UserSocialAccount extends Model
{
    use HasUlids, HasFactory;

    protected $fillable = [
        'user_id', 'provider', 'provider_id',
        'access_token', 'refresh_token', 'token_expires_at',
    ];

    protected function casts(): array
    {
        return [
            'token_expires_at' => 'datetime',
            // $timestamps = false means Eloquent no longer auto-casts
            // created_at as a date the way it does when timestamps are
            // enabled, so it must be declared explicitly here.
            'created_at' => 'datetime',
        ];
    }

    // The table only has a `created_at` column (no `updated_at`), so we
    // can't use Eloquent's built-in $timestamps support (it manages both
    // columns together and would error trying to write a non-existent
    // `updated_at`). Populate `created_at` manually instead. The old
    // `protected $createdAt = 'created_at';` property was dead code: that
    // setting is only consulted when $timestamps is true, so it never
    // actually took effect while $timestamps was false.
    public $timestamps = false;

    protected static function booted(): void
    {
        static::creating(function (self $account) {
            $account->created_at ??= now();
        });
    }
}
