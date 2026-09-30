<?php

namespace App\Modules\Auth\Http\Controllers;

use App\Modules\Auth\Models\User;
use App\Modules\Auth\Services\EmailVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmailVerificationController
{
    public function __construct(
        private readonly EmailVerificationService $verificationService,
    ) {}

    public function send(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email already verified.']);
        }

        $this->verificationService->send($user);

        return response()->json(['message' => 'Verification email sent.']);
    }

    public function verify(Request $request, string $id, string $hash): JsonResponse
    {
        $user = User::findOrFail($id);

        if ($this->verificationService->verify($user, $hash)) {
            return response()->json(['message' => 'Email verified successfully.']);
        }

        return response()->json(['message' => 'Invalid verification link.'], 400);
    }
}
