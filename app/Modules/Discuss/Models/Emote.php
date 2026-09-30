<?php

namespace App\Modules\Discuss\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class Emote extends Model
{
    use HasUlids, HasFactory;

    protected $table = 'discuss_emotes';

    protected $fillable = [
        'room_id', 'code', 'name', 'image_url', 'unicode',
        'is_animated', 'is_active', 'uploaded_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'is_animated' => 'boolean',
            'is_active' => 'boolean',
            // $timestamps = false means Eloquent no longer auto-casts
            // created_at as a date the way it does when timestamps are
            // enabled, so it must be declared explicitly here.
            'created_at' => 'datetime',
        ];
    }

    // The table only has a `created_at` column (no `updated_at`), so we
    // can't use Eloquent's built-in $timestamps support (it manages both
    // columns together and would error trying to write a non-existent
    // `updated_at`). Populate `created_at` manually instead.
    public $timestamps = false;

    protected static function booted(): void
    {
        static::creating(function (self $emote) {
            $emote->created_at ??= now();
        });
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeGlobal($query)
    {
        return $query->whereNull('room_id');
    }

    public function scopeForRoom($query, string $roomId)
    {
        return $query->where(function ($q) use ($roomId) {
            $q->whereNull('room_id')->orWhere('room_id', $roomId);
        });
    }
}
