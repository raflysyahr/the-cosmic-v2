<?php

namespace App\Modules\Auth\Http\Controllers;

use App\Modules\Auth\Http\Requests\PasswordResetRequest;
use App\Modules\Auth\Services\PasswordResetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PasswordResetController
{
    public function __construct(
        private readonly PasswordResetService $resetService,
    ) {}

    public function sendLink(PasswordResetRequest $request): JsonResponse
    {
        $this->resetService->sendLink($request->input('email'));

        return response()->json(['message' => 'Password reset link sent.']);
    }

    public function reset(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'token' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $result = $this->resetService->reset(
            $request->input('email'),
            $request->input('token'),
            $request->input('password'),
        );

        if ($result) {
            return response()->json(['message' => 'Password reset successfully.']);
        }

        return response()->json(['message' => 'Invalid or expired token.'], 400);
    }
}
