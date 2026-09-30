<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChapterPage extends Model
{
    protected $primaryKey = 'chapter_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'chapter_id', 'images',
    ];

    protected $casts = [
        'images' => 'array',
    ];

    public function chapter(): BelongsTo
    {
        return $this->belongsTo(Chapter::class);
    }
}
