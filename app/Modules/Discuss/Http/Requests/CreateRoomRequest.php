<?php

namespace App\Modules\Discuss\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateRoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['required', 'string', 'max:100', 'unique:discuss_rooms,slug'],
            'description' => ['nullable', 'string'],
            'cover_url' => ['nullable', 'url'],
            'type' => ['required', 'string', 'in:public,private,invite_only'],
            'context_type' => ['nullable', 'string', 'max:50'],
            'context_id' => ['nullable', 'string', 'max:26'],
            'settings' => ['nullable', 'array'],
        ];
    }
}
