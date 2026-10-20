<?php

namespace App\Modules\Discuss\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRoomSettingsRequest extends FormRequest
{
    /**
     * Izin edit per-room (owner/admin) diperiksa di RoomController::update, karena butuh
     * room yang sedang dituju. Di sini cukup memastikan pemanggilnya login.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return array_merge([
            'name' => ['sometimes', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'cover_url' => ['nullable', 'url'],
            'type' => ['sometimes', 'string', 'in:public,private,invite_only'],
            'is_active' => ['sometimes', 'boolean'],
            // Konteks room (DM, komik) dan pemiliknya hanya boleh ditentukan server.
            'context_type' => ['prohibited'],
            'context_id' => ['prohibited'],
            'owner_user_id' => ['prohibited'],
            'slug' => ['prohibited'],
        ], CreateRoomRequest::settingsRules());
    }
}
