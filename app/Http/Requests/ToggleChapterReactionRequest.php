<?php

namespace App\Http\Requests;

use App\Services\ChapterReactionService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ToggleChapterReactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reaction' => ['required', 'string', Rule::in(ChapterReactionService::KEYS)],
        ];
    }
}
