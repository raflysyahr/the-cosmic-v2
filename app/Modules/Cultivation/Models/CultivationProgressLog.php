<?php

namespace App\Modules\Cultivation\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class CultivationProgressLog extends Model
{
    use HasUlids;

    protected $fillable = [
        'user_id', 'source', 'reference', 'amount',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
        ];
    }

    // Tidak ada relationship (user()) per AGENTS.md §2.
}
