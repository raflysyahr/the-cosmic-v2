<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Chapter extends Model
{
    use HasUlids, HasFactory;

    protected $fillable = [
        'series_id', 'index', 'title', 'released_at',
    ];

    protected $casts = [
        'released_at' => 'datetime',
    ];

    public function series(): BelongsTo
    {
        return $this->belongsTo(Series::class);
    }

    // images (array URL halaman) sengaja dipisah ke tabel chapter_pages
    // supaya query list chapter (Detail page) tidak ikut nge-load gambar
    // yang cuma dibutuhkan Reader saat buka 1 chapter spesifik.
    public function pages(): HasOne
    {
        return $this->hasOne(ChapterPage::class);
    }
}
