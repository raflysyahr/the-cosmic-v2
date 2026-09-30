<?php

namespace App\Modules\Discuss\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class CpEvent extends Model
{
    use HasUlids;

    protected $table = 'discuss_cp_events';

    protected $fillable = [
        'name', 'multiplier_pct', 'sources', 'room_id',
        'starts_at', 'ends_at', 'is_active', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'multiplier_pct' => 'integer',
            'sources' => 'array',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    /** Event yang sedang berlangsung saat ini (belum difilter source/room). */
    public function scopeRunning($query)
    {
        return $query->where('is_active', true)
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>', now());
    }

    public function appliesTo(string $source, ?string $roomId): bool
    {
        if ($this->sources !== null && $this->sources !== [] && ! in_array($source, $this->sources, true)) {
            return false;
        }

        return $this->room_id === null || (string) $this->room_id === (string) $roomId;
    }
}
