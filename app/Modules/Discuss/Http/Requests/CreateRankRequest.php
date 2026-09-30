<?php

namespace App\Modules\Discuss\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateRankRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50'],
            'label_color' => ['required', 'string', 'max:7'],
            'icon_url' => ['nullable', 'url'],
            'min_xp' => ['required', 'integer', 'min:0'],
            'order' => ['required', 'integer', 'min:1'],
            'perks' => ['nullable', 'array'],
        ];
    }
}
