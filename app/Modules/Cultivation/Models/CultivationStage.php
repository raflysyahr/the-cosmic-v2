<?php

namespace App\Modules\Cultivation\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class CultivationStage extends Model
{
    use HasUlids;

    protected $fillable = [
        'realm_id', 'stage', 'level', 'start_progress', 'end_progress',
    ];

    protected function casts(): array
    {
        return [
            'stage' => 'integer',
            'level' => 'integer',
            'start_progress' => 'integer',
            'end_progress' => 'integer',
        ];
    }

    // Tidak ada relationship (realm()) per AGENTS.md §2.
}
