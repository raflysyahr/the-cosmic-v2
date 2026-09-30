<?php

namespace App\Modules\Discuss\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadEmoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50', 'unique:discuss_emotes,code'],
            'name' => ['required', 'string', 'max:100'],
            'image' => ['required', 'image', 'max:512', 'mimes:png,gif,webp'],
            'room_id' => ['nullable', 'string', 'max:26'],
        ];
    }
}
