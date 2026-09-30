<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReadingHistory extends Model
{
    use HasUlids, HasFactory;

    protected $fillable = [
        'user_id', 'slug', 'title', 'cover_image',
        'read_chapters', 'last_chapter_index', 'last_chapter_url',
    ];

    protected function casts(): array
    {
        return [
            'read_chapters' => 'array',
            'last_chapter_index' => 'integer',
        ];
    }
}
