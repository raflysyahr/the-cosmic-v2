<?php

namespace App\Modules\Discuss\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class AnnouncementReaction extends Model
{
    use HasUlids;

    protected $table = 'discuss_announcement_reactions';

    /** Palet reaksi yang diizinkan (urutan = urutan tampil di UI). */
    public const PALETTE = ['❤️', '👍', '😂', '😮', '😢', '🔥'];

    protected $fillable = ['announcement_id', 'user_id', 'emoji'];

    // Tabel hanya punya created_at (tanpa updated_at); lihat catatan di Reaction model.
    public $timestamps = false;

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $reaction) {
            $reaction->created_at ??= now();
        });
    }
}
