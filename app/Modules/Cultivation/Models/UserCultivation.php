<?php

namespace App\Modules\Cultivation\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class UserCultivation extends Model
{
    use HasUlids;

    protected $table = 'user_cultivation';

    protected $fillable = [
        'user_id', 'realm_id', 'stage', 'progress',
    ];

    protected function casts(): array
    {
        return [
            'stage' => 'integer',
            'progress' => 'integer',
        ];
    }

    // Tidak ada relationship (user(), realm()) per AGENTS.md §2 — query
    // manual dengan select() di CultivationService, lihat pola yang
    // sama di Discuss/Models/Message.php dkk. Level dihitung manual di
    // CultivationService (realm.level_start + stage - 1), bukan lewat
    // accessor yang bergantung relasi.
}
