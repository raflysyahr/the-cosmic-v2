<?php

namespace App\Modules\Discuss\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReactToCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'emote_id' => ['required', 'string', 'max:26'],
        ];
    }
}
