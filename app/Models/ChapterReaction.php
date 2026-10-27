<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class ChapterReaction extends Model
{
    use HasUlids;

    protected $fillable = ['series_slug', 'chapter_index', 'user_id', 'reaction'];

    // Tabel hanya punya created_at (tanpa updated_at), sama seperti AnnouncementReaction.
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'chapter_index' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $reaction) {
            $reaction->created_at ??= now();
        });
    }
}
