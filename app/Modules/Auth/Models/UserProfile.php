<?php

namespace App\Modules\Auth\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class UserProfile extends Model
{
    use HasUlids, HasFactory;

    protected $fillable = [
        'user_id', 'bio', 'website_url', 'location', 'preferences',
    ];

    protected function casts(): array
    {
        return [
            'preferences' => 'array',
            // $timestamps = false means Eloquent no longer auto-casts
            // updated_at as a date the way it does when timestamps are
            // enabled, so it must be declared explicitly here.
            'updated_at' => 'datetime',
        ];
    }

    // The table only has an `updated_at` column (no `created_at`), so we
    // can't use Eloquent's built-in $timestamps support (it manages both
    // columns together and would error trying to write a non-existent
    // `created_at`). Populate `updated_at` manually on every save instead.
    // The old `protected $updatedAt = 'updated_at';` property was dead
    // code: that setting is only consulted when $timestamps is true, so it
    // never actually took effect while $timestamps was false.
    public $timestamps = false;

    protected static function booted(): void
    {
        static::saving(function (self $profile) {
            $profile->updated_at = now();
        });
    }
}
