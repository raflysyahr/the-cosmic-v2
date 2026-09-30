<?php

namespace App\Modules\Discuss\Models;

use App\Modules\Discuss\Database\Factories\MemberFactory;
use App\Modules\Discuss\Enums\MemberRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class Member extends Model
{
    use HasUlids, HasFactory;

    protected $table = 'discuss_members';

    protected $fillable = [
        'room_id', 'user_id', 'role', 'rank_id', 'xp_points',
        'muted_until', 'is_banned', 'last_read_at', 'joined_at',
    ];

    protected function casts(): array
    {
        return [
            'role' => MemberRole::class,
            'xp_points' => 'integer',
            'muted_until' => 'datetime',
            'is_banned' => 'boolean',
            'last_read_at' => 'datetime',
            'joined_at' => 'datetime',
        ];
    }

    public $timestamps = false;

    protected static function newFactory(): MemberFactory
    {
        return MemberFactory::new();
    }
}
