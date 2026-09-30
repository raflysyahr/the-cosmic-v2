<?php

namespace App\Modules\Discuss\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendMessageRequest extends FormRequest
{
    // Shared with UpdateMessageRequest so send and edit accept the same uploads.
    public const IMAGE_RULES = ['nullable', 'image', 'mimes:png,jpg,jpeg,gif,webp', 'max:8192'];

    // Video uploads. Duration / size / dimensions are read client-side (no ffmpeg
    // on the server), and the client also uploads a poster frame as `thumbnail`.
    public const VIDEO_RULES = ['nullable', 'file', 'max:51200', 'mimes:mp4,mov,webm'];
    public const THUMBNAIL_RULES = ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'];

    // Whitelist only — never allow executable/script/html/svg types onto the public disk.
    public const FILE_RULES = [
        'nullable', 'file', 'max:20480',
        'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv,rtf,odt,ods,odp,zip,rar,7z,mp3,wav,ogg,mp4,mov,webm',
    ];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'body' => ['required_without_all:attachments,image,file,video', 'nullable', 'string'],
            'attachments' => ['required_without_all:body,image,file,video', 'nullable', 'array'],
            'attachments.*' => ['url'],
            // Direct image upload (multipart) — an alternative to passing
            // already-hosted URLs via `attachments`. The controller stores
            // it and turns it into an attachment URL itself.
            'image' => self::IMAGE_RULES,
            // Generic document upload (multipart).
            'file' => self::FILE_RULES,
            'video' => self::VIDEO_RULES,
            'thumbnail' => self::THUMBNAIL_RULES,
            'duration' => ['nullable', 'numeric', 'min:0', 'max:86400'],
            'width' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'height' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'reply_to_id' => ['nullable', 'string', 'max:26'],
            'type' => ['nullable', 'string', 'in:text,image,file,video,sticker,system'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
