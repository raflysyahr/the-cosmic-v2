<?php

namespace App\Modules\Auth\Listeners;

use App\Modules\Auth\Events\UserRegistered;
use App\Modules\Auth\Services\EmailVerificationService;

class SendVerificationEmail
{
    public function __construct(
        private readonly EmailVerificationService $verificationService,
    ) {}

    public function handle(UserRegistered $event): void
    {
        $this->verificationService->send($event->user);
    }
}
