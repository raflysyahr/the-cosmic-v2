<?php

namespace App\Modules\Auth\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuthSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'id', 'user_id', 'ip_address', 'user_agent', 'payload', 'last_activity',
    ];

    public $timestamps = false;

    protected $casts = [
        'last_activity' => 'integer',
    ];
}
