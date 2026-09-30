<?php

namespace App\Modules\Discuss\Http\Requests;

use App\Modules\Discuss\Services\CpSettingsService;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCpSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Otorisasi admin dicek di CpSettingsService (AGENTS.md §4).
        return true;
    }

    public function rules(): array
    {
        $rules = [];

        foreach (CpSettingsService::TYPES as $key => $type) {
            $rules[$key] = $type === 'bool'
                ? ['sometimes', 'nullable', 'boolean']
                : ['sometimes', 'nullable', 'integer', 'min:' . ($key === 'burst_window_seconds' ? 1 : 0), 'max:100000'];
        }

        return $rules;
    }
}
