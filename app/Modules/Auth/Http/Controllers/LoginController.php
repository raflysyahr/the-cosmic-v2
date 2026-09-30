<?php

namespace App\Modules\Auth\Http\Controllers;

use App\Modules\Auth\Data\UserData;
use App\Modules\Auth\Http\Requests\LoginRequest;
use App\Modules\Auth\Services\AuthService;
use Illuminate\Http\JsonResponse;

class LoginController
{
    public function __construct(
        private readonly AuthService $authService,
    ) {}

    public function store(LoginRequest $request): JsonResponse
    {
        $user = $this->authService->login(
            $request->input('email'),
            $request->input('password'),
            $request->boolean('remember'),
        );

        auth()->login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        return response()->json([
            'message' => 'Login successful.',
            'user' => UserData::fromModel($user),
        ]);
    }
}
