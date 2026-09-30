<?php

namespace App\Modules\Discuss\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class Rank extends Model
{
    use HasUlids, HasFactory;

    protected $table = 'discuss_ranks';

    protected $fillable = [
        'room_id', 'name', 'label_color', 'icon_url',
        'min_xp', 'order', 'perks',
    ];

    protected function casts(): array
    {
        return [
            'min_xp' => 'integer',
            'order' => 'integer',
            'perks' => 'array',
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
        static::creating(function (self $rank) {
            $rank->created_at ??= now();
        });
    }

    public function scopeForRoom($query, ?string $roomId)
    {
        return $query->where(function ($q) use ($roomId) {
            $q->whereNull('room_id')->orWhere('room_id', $roomId);
        });
    }

    public function scopeGlobal($query)
    {
        return $query->whereNull('room_id');
    }
}
