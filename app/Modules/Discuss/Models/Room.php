<?php

namespace App\Modules\Discuss\Models;

use App\Modules\Discuss\Database\Factories\RoomFactory;
use App\Modules\Discuss\Enums\RoomType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    use HasUlids, HasFactory;

    protected $table = 'discuss_rooms';

    protected $fillable = [
        'slug', 'name', 'description', 'cover_url', 'type',
        'owner_user_id', 'context_type', 'context_id',
        'is_active', 'settings',
    ];

    protected function casts(): array
    {
        return [
            'type' => RoomType::class,
            'is_active' => 'boolean',
            'settings' => 'array',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopePublic($query)
    {
        return $query->where('type', RoomType::Public);
    }

    protected static function newFactory(): RoomFactory
    {
        return RoomFactory::new();
    }
}
