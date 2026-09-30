<?php

namespace App\Modules\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'display_name' => ['sometimes', 'string', 'max:100'],
            'username' => [
                'sometimes', 'string', 'max:50', 'alpha_dash',
                Rule::unique('users', 'username')->ignore($this->user()?->id),
            ],
            'avatar' => ['sometimes', 'image', 'mimes:png,jpg,gif,webp', 'max:2048'],
            'bio' => ['sometimes', 'nullable', 'string', 'max:500'],
            'website_url' => ['sometimes', 'nullable', 'url'],
            'location' => ['sometimes', 'nullable', 'string', 'max:100'],
            'preferences' => ['sometimes', 'array'],
        ];
    }
}
