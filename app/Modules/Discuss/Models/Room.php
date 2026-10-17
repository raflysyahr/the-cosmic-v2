<?php

namespace App\Modules\Discuss\Models;

use App\Modules\Discuss\Database\Factories\RoomFactory;
use App\Modules\Discuss\Enums\RoomType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class Room extends Model
{
    use HasUlids, HasFactory;

    protected $table = 'discuss_rooms';

    protected $fillable = [
        'slug', 'name', 'description', 'cover_url', 'type',
        'owner_user_id', 'context_type', 'context_id',
        'is_active', 'settings',
    ];

    protected function casts(): array
    {
        return [
            'type' => RoomType::class,
            'is_active' => 'boolean',
            'settings' => 'array',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopePublic($query)
    {
        return $query->where('type', RoomType::Public);
    }

    /**
     * Kunci `settings` yang rahasia dan tidak boleh sampai ke klien. `invite_link` adalah
     * kredensial untuk join room invite-only: siapa pun yang membacanya bisa masuk. Token
     * hanya diberikan sekali lewat POST /rooms/{slug}/invite kepada owner/moderator/admin.
     */
    public const SECRET_SETTINGS = ['invite_link'];

    /**
     * Settings yang aman dikirim ke klien (tanpa kunci rahasia). Kode server tetap membaca
     * `$room->settings` langsung.
     */
    public function clientSettings(): array
    {
        return Arr::except($this->settings ?? [], self::SECRET_SETTINGS);
    }

    /**
     * Aman-secara-default: semua serialisasi model (props Inertia, respons JSON, broadcast)
     * membuang kunci rahasia, jadi jalur baru yang lupa memfilter tidak ikut membocorkannya.
     * Tidak mengubah atribut model, hanya hasil array-nya.
     */
    public function toArray(): array
    {
        $array = parent::toArray();

        if (isset($array['settings']) && is_array($array['settings'])) {
            $array['settings'] = Arr::except($array['settings'], self::SECRET_SETTINGS);
        }

        return $array;
    }

    protected static function newFactory(): RoomFactory
    {
        return RoomFactory::new();
    }
}
