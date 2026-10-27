<?php

namespace App\Modules\Discuss\Http\Requests;

use App\Modules\Discuss\Services\CommentService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Hanya `body` dan `reply_to_id`. Room, chapter, dan penanda komentar SENGAJA tidak bisa
 * dikirim klien: semuanya diturunkan server dari slug/index di URL (lihat CommentService).
 */
class PostCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:' . CommentService::MAX_BODY],
            'reply_to_id' => ['nullable', 'string', 'max:26'],
        ];
    }
}
