<?php

namespace App\Modules\Discuss\Models;

use Illuminate\Database\Eloquent\Model;

class AnnouncementSeen extends Model
{
    protected $table = 'discuss_story_seen';

    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = ['user_id', 'seen_at'];

    protected function casts(): array
    {
        return ['seen_at' => 'datetime'];
    }
}
