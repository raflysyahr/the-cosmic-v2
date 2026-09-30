<?php

namespace App\Modules\Discuss\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Not `required`: an image/file message may have its caption cleared.
            // MessageService::edit() rejects an empty body on plain text messages.
            'body' => ['nullable', 'string'],
            // Optional new attachment — replaces the message's current one.
            'image' => SendMessageRequest::IMAGE_RULES,
            'file' => SendMessageRequest::FILE_RULES,
            'video' => SendMessageRequest::VIDEO_RULES,
            'thumbnail' => SendMessageRequest::THUMBNAIL_RULES,
            'duration' => ['nullable', 'numeric', 'min:0', 'max:86400'],
            'width' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'height' => ['nullable', 'integer', 'min:1', 'max:10000'],
        ];
    }
}
