<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Genre extends Model
{
    use HasUlids, HasFactory;

    protected $fillable = [
        'name', 'slug', 'description',
    ];

    public function series(): BelongsToMany
    {
        return $this->belongsToMany(Series::class, 'genre_series');
    }
}
