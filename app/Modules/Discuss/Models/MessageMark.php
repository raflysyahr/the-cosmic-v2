<?php

namespace App\Modules\Discuss\Models;

use App\Modules\Discuss\Enums\MarkKind;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class MessageMark extends Model
{
    use HasUlids;

    protected $table = 'discuss_message_marks';

    protected $fillable = ['room_id', 'message_id', 'marked_by', 'kind'];

    protected function casts(): array
    {
        return [
            'kind' => MarkKind::class,
            'created_at' => 'datetime',
        ];
    }

    // Hanya created_at (tanpa updated_at) — pola sama dengan Reaction/Rank.
    public $timestamps = false;

    protected static function booted(): void
    {
        static::creating(function (self $mark) {
            $mark->created_at ??= now();
        });
    }
}
