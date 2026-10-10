<?php

namespace App\Modules\Discuss\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AnnouncementRequest extends FormRequest
{
    /** Maksimal foto/video per post. */
    public const MAX_MEDIA = 10;

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

            // Carousel: sampai 10 foto/video per post (multipart, key berindeks:
            // media[0], media[1], …). Metadata tiap file memakai indeks yang sama
            // (thumbnails[0], durations[0], widths[0], heights[0]) dan dibaca di
            // browser. Aturan sama dengan chat.
            'media' => ['sometimes', 'nullable', 'array', 'max:' . self::MAX_MEDIA],
            'media.*' => [
                'file',
                'mimes:png,jpg,jpeg,gif,webp,mp4,mov,webm',
                'max:51200', // batas video (KB); foto dibatasi lebih ketat oleh closure di bawah
                function (string $attribute, mixed $value, \Closure $fail) {
                    if ($value instanceof \Illuminate\Http\UploadedFile
                        && str_starts_with((string) $value->getMimeType(), 'image/')
                        && $value->getSize() > 8192 * 1024) {
                        $fail('Each photo may not be greater than 8 MB.');
                    }
                },
            ],
            'thumbnails' => ['sometimes', 'nullable', 'array'],
            'thumbnails.*' => SendMessageRequest::THUMBNAIL_RULES,
            'durations.*' => ['nullable', 'numeric', 'min:0', 'max:86400'],
            'widths.*' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'heights.*' => ['nullable', 'integer', 'min:1', 'max:10000'],

            // Edit: kirim sync_media=1 + id item lama yang dipertahankan (urutan = urutan
            // tampil); item lama yang tidak disebut dihapus. File baru ditambahkan di akhir.
            'sync_media' => ['sometimes', 'boolean'],
            'keep_media' => ['sometimes', 'nullable', 'array', 'max:' . self::MAX_MEDIA],
            'keep_media.*' => ['string', 'max:40'],
        ];
    }
}
