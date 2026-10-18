<?php

namespace App\Modules\Auth\Exceptions;

use RuntimeException;

/**
 * Login social ditolak karena alasan yang aman ditampilkan ke user (bukan error sistem).
 * `$reason` adalah kode singkat yang diteruskan ke halaman login sebagai `?social_error=`.
 */
class SocialAuthException extends RuntimeException
{
    public const EMAIL_IN_USE = 'email_in_use';
    public const BANNED = 'banned';

    public function __construct(public readonly string $reason)
    {
        parent::__construct("Social login rejected: {$reason}");
    }
}
