<?php

namespace App\Modules\Discuss\Http\Requests;

use App\Modules\Discuss\Enums\CpSource;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CpEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Otorisasi admin dicek di CpEventService (AGENTS.md §4).
        return true;
    }

    public function rules(): array
    {
        // POST = buat baru (field wajib); PUT = update parsial.
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'name' => [$required, 'string', 'max:100'],
            'multiplier' => [$required, 'numeric', 'min:1', 'max:10'],
            'sources' => ['sometimes', 'nullable', 'array'],
            'sources.*' => ['string', Rule::in(CpSource::earnable())],
            'room_id' => ['sometimes', 'nullable', 'string', 'exists:discuss_rooms,id'],
            'starts_at' => [$required, 'date'],
            'ends_at' => [$required, 'date'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
