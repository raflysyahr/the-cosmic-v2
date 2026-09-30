<?php

namespace App\Modules\Auth\Http\Controllers;

use App\Modules\Auth\Data\RegisterData;
use App\Modules\Auth\Data\UserData;
use App\Modules\Auth\Http\Requests\RegisterRequest;
use App\Modules\Auth\Services\AuthService;
use Illuminate\Http\JsonResponse;

class RegisterController
{
    public function __construct(
        private readonly AuthService $authService,
    ) {}

    public function store(RegisterRequest $request): JsonResponse
    {
        $data = new RegisterData(
            name: $request->input('name'),
            username: $request->input('username'),
            email: $request->input('email'),
            password: $request->input('password'),
        );

        $user = $this->authService->register($data);

        auth()->login($user);
        request()->session()->regenerate();

        return response()->json([
            'message' => 'Registration successful.',
            'user' => UserData::fromModel($user),
        ], 201);
    }
}
