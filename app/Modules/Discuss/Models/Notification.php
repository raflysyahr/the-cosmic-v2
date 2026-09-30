<?php

namespace App\Modules\Discuss\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasUlids, HasFactory;

    protected $table = 'discuss_notifications';

    protected $fillable = [
        'user_id', 'type', 'actor_user_id', 'payload', 'is_read',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'is_read' => 'boolean',
            // $timestamps = false means Eloquent no longer auto-casts
            // created_at as a date the way it does when timestamps are
            // enabled, so it must be declared explicitly here.
            'created_at' => 'datetime',
        ];
    }

    // The table only has a `created_at` column (no `updated_at`), so we
    // can't use Eloquent's built-in $timestamps support (it manages both
    // columns together and would error trying to write a non-existent
    // `updated_at`). Populate `created_at` manually instead — this also
    // fixes NotificationController::index()'s `orderBy('created_at', 'desc')`,
    // which was previously ordering by a column that was always NULL.
    public $timestamps = false;

    protected static function booted(): void
    {
        static::creating(function (self $notification) {
            $notification->created_at ??= now();
        });
    }

    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }
}
