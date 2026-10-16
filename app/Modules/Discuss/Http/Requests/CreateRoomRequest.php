<?php

namespace App\Modules\Discuss\Http\Requests;

use App\Modules\Auth\Enums\UserRole;
use App\Modules\Auth\Enums\UserStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateRoomRequest extends FormRequest
{
    /**
     * Slug yang bentrok dengan rute tetap `/discuss/...` (story, leaderboard, invite)
     * atau dengan area lain aplikasi, sehingga room-nya tidak akan pernah terbuka.
     */
    public const RESERVED_SLUGS = ['story', 'leaderboard', 'invite', 'admin', 'api', 'direct', 'comic'];

    /**
     * Membuat room hanya boleh dilakukan user dengan role platform `admin` (users.role)
     * yang akunnya aktif. Pembuatnya otomatis menjadi owner sekaligus admin room-nya
     * (lihat RoomService::create). User biasa tidak bisa membuat room lewat API.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && $user->role === UserRole::Admin
            && $user->status === UserStatus::Active;
    }

    /**
     * Aturan `settings` yang dibagi dengan UpdateRoomSettingsRequest, supaya keduanya
     * tidak bisa saling menyimpang. Hanya kunci yang dikenal; `invite_link`, `is_direct`,
     * dst. dikelola server.
     */
    public static function settingsRules(): array
    {
        return [
            'settings' => ['nullable', 'array:slow_mode_seconds,max_members,xp_per_message'],
            'settings.slow_mode_seconds' => ['nullable', 'integer', 'min:0', 'max:3600'],
            'settings.max_members' => ['nullable', 'integer', 'min:2', 'max:5000'],
            'settings.xp_per_message' => ['nullable', 'integer', 'min:0', 'max:100'],
        ];
    }

    public function rules(): array
    {
        return array_merge([
            'name' => ['required', 'string', 'max:100'],
            'slug' => [
                'required',
                'string',
                'max:100',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::notIn(self::RESERVED_SLUGS),
                // Prefix ini dipakai server untuk room DM dan room komik.
                'not_regex:/^(direct|comic)-/',
                'unique:discuss_rooms,slug',
            ],
            'description' => ['nullable', 'string'],
            'cover_url' => ['nullable', 'url'],
            'type' => ['required', 'string', 'in:public,private,invite_only'],
            // Konteks room (DM, komik) hanya boleh ditentukan server. Kalau klien boleh
            // mengisinya, penyerang bisa membuat room yang menyamar sebagai DM dua orang
            // lain dan membaca percakapan mereka.
            'context_type' => ['prohibited'],
            'context_id' => ['prohibited'],
        ], self::settingsRules());
    }
}
