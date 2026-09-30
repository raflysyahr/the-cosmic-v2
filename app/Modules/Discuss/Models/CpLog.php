<?php

namespace App\Modules\Discuss\Models;

use App\Modules\Discuss\Enums\CpSource;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class CpLog extends Model
{
    use HasUlids;

    protected $table = 'discuss_cp_logs';

    protected $fillable = [
        'user_id', 'room_id', 'source', 'reference', 'subject_id',
        'base_amount', 'multiplier_pct', 'amount', 'event_id',
    ];

    protected function casts(): array
    {
        return [
            'source' => CpSource::class,
            'base_amount' => 'integer',
            'multiplier_pct' => 'integer',
            'amount' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    // Hanya ada created_at (tanpa updated_at) — pola sama dengan Rank.
    public $timestamps = false;

    protected static function booted(): void
    {
        static::creating(function (self $log) {
            $log->created_at ??= now();
        });
    }
}
