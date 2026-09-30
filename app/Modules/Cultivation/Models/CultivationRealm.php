<?php

namespace App\Modules\Cultivation\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class CultivationRealm extends Model
{
    use HasUlids;

    protected $fillable = [
        'era_id', 'name', 'full_name', 'description', 'level_start', 'level_end',
        'stage_required', 'realm_total_required', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'level_start' => 'integer',
            'level_end' => 'integer',
            'stage_required' => 'integer',
            'realm_total_required' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    // Tidak ada relationship (era(), stages(), userCultivations()) per
    // AGENTS.md §2 — query manual di CultivationService.
}
