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
        // POST = buat baru; PUT = update parsial. Post bermedia (foto/video) boleh
        // tanpa judul & caption; post tanpa media tetap wajib judul + isi.
        $required = $this->isMethod('POST') ? 'required_without:media' : 'sometimes';

        return [
            'title' => [$required, 'nullable', 'string', 'max:120'],
            'body' => [$required, 'nullable', 'string', 'max:5000'],
            // Hanya http/https — tautan ini dirender sebagai <a href> di browser user.
            'link_url' => ['sometimes', 'nullable', 'url:http,https', 'max:500'],
            'is_pinned' => ['sometimes', 'boolean'],
            'published_at' => ['sometimes', 'nullable', 'date'],

            // Satu foto ATAU video (multipart). Aturan sama dengan chat.
            'media' => [
                'sometimes', 'nullable', 'file',
                'mimes:png,jpg,jpeg,gif,webp,mp4,mov,webm',
                'max:51200', // batas video (KB); gambar dibatasi lebih ketat oleh closure di bawah
                function (string $attribute, mixed $value, \Closure $fail) {
                    if ($value instanceof \Illuminate\Http\UploadedFile
                        && str_starts_with((string) $value->getMimeType(), 'image/')
                        && $value->getSize() > 8192 * 1024) {
                        $fail('The photo may not be greater than 8 MB.');
                    }
                },
            ],
            'thumbnail' => SendMessageRequest::THUMBNAIL_RULES,
            'duration' => ['nullable', 'numeric', 'min:0', 'max:86400'],
            'width' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'height' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'remove_media' => ['sometimes', 'boolean'],
        ];
    }
}
