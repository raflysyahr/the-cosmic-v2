<?php

namespace App\Modules\Discuss\Http\Controllers;

use App\Modules\Discuss\Services\AchievementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AchievementController
{
    public function __construct(
        private readonly AchievementService $achievements,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'achievements' => $this->achievements->progress((string) $request->user()->id),
        ]);
    }
}
