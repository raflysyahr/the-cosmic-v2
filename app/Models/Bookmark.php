<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Bookmark extends Model
{
    use HasUlids, HasFactory;

    protected $fillable = [
        'user_id', 'slug', 'title', 'cover_image', 'format',
    ];
}
