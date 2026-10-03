<?php

namespace App\Modules\Discuss\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class Announcement extends Model
{
    use HasUlids;

    protected $table = 'discuss_announcements';

    protected $fillable = [
        'title', 'body', 'link_url', 'is_pinned', 'published_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_pinned' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    /** Sudah tayang (published_at tidak null dan sudah lewat). */
    public function scopePublished($query)
    {
        return $query->whereNotNull('published_at')->where('published_at', '<=', now());
    }
}
