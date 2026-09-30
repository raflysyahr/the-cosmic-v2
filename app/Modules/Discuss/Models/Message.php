<?php

namespace App\Modules\Discuss\Models;

use App\Modules\Discuss\Database\Factories\MessageFactory;
use App\Modules\Discuss\Enums\MessageType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    use HasUlids, HasFactory;

    protected $table = 'discuss_messages';

    protected $fillable = [
        'room_id', 'user_id', 'reply_to_id', 'type', 'body',
        'attachments', 'is_edited', 'is_deleted', 'deleted_by_user_id',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'type' => MessageType::class,
            'attachments' => 'array',
            'is_edited' => 'boolean',
            'is_deleted' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function scopeNotDeleted($query)
    {
        return $query->where('is_deleted', false);
    }

    protected static function newFactory(): MessageFactory
    {
        return MessageFactory::new();
    }
}
