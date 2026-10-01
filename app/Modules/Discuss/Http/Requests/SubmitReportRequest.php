<?php

namespace App\Modules\Discuss\Http\Requests;

use App\Modules\Discuss\Enums\ReportReason;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubmitReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Otorisasi (member aktif, bukan pesan sendiri, dll.) di ReportService (AGENTS.md §4).
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', Rule::in(ReportReason::values())],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
