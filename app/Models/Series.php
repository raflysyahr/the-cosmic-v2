<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Series extends Model
{
    use HasUlids, HasFactory;

    protected $table = 'series';

    protected $fillable = [
        'slug', 'title', 'native_title', 'cover', 'rating', 'status',
        'type', 'is_hot', 'total_chapters', 'author', 'anime_adaptation',
        'synopsis', 'views', 'released_at',
    ];

    protected $casts = [
        'is_hot' => 'boolean',
        'anime_adaptation' => 'boolean',
        'rating' => 'decimal:1',
        'released_at' => 'datetime',
    ];

    public function genres(): BelongsToMany
    {
        return $this->belongsToMany(Genre::class, 'genre_series');
    }

    public function chapters(): HasMany
    {
        return $this->hasMany(Chapter::class)->orderBy('index');
    }

    public function latestChapter(): HasOne
    {
        return $this->hasOne(Chapter::class)->latestOfMany('index');
    }
}
