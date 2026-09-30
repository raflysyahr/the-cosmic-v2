<?php

namespace App\Modules\Auth\Http\Controllers;

use App\Modules\Auth\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LogoutController
{
    public function __construct(
        private readonly AuthService $authService,
    ) {}

    public function destroy(Request $request): JsonResponse
    {
        // This route is protected by `auth:sanctum` (see auth.php), under
        // which `auth()` resolves to a RequestGuard that has no logout()
        // method — only the underlying session guard ('web', see
        // config/auth.php) does. LoginController logs in via the 'web'
        // guard, so we must log out via the same guard explicitly.
        auth('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }

}
