<?php

namespace App\Modules\Discuss\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRoomSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'cover_url' => ['nullable', 'url'],
            'type' => ['sometimes', 'string', 'in:public,private,invite_only'],
            'is_active' => ['sometimes', 'boolean'],
            'settings' => ['nullable', 'array'],
        ];
    }
}
