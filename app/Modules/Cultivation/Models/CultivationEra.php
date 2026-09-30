<?php

namespace App\Modules\Cultivation\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class CultivationEra extends Model
{
    use HasUlids;

    protected $fillable = [
        'name', 'resource_name', 'resource_slug', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    // Tidak ada relationship (realms()) per AGENTS.md §2.
}
