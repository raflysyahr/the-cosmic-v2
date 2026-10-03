<?php

namespace App\Modules\Discuss\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AnnouncementRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Otorisasi admin dicek di AnnouncementService (AGENTS.md §4).
        return true;
    }

    public function rules(): array
    {
        // POST = buat baru (judul & isi wajib); PUT = update parsial.
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'title' => [$required, 'string', 'max:120'],
            'body' => [$required, 'string', 'max:5000'],
            // Hanya http/https — tautan ini dirender sebagai <a href> di browser user.
            'link_url' => ['sometimes', 'nullable', 'url:http,https', 'max:500'],
            'is_pinned' => ['sometimes', 'boolean'],
            'published_at' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
