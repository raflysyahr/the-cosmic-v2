<?php

namespace App\Modules\Discuss\Http\Requests;

use App\Modules\Discuss\Enums\PenaltyType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ResolveReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Otorisasi moderator/admin room di ReportService (AGENTS.md §4).
        return true;
    }

    public function rules(): array
    {
        return [
            'outcome' => ['required', 'string', Rule::in(['valid', 'dismissed'])],
            'penalty' => ['sometimes', 'nullable', 'string', Rule::in(PenaltyType::values())],
            'delete_message' => ['sometimes', 'boolean'],
        ];
    }
}
