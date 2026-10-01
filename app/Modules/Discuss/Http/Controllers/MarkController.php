<?php

namespace App\Modules\Discuss\Http\Controllers;

use App\Modules\Discuss\Models\Room;
use App\Modules\Discuss\Services\MarkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MarkController
{
    public function __construct(
        private readonly MarkService $markService,
    ) {}

    public function helpful(Request $request, string $slug, string $messageId): JsonResponse
    {
        $room = Room::where('slug', $slug)->firstOrFail();

        return response()->json(
            $this->markService->toggleHelpful($room->id, $messageId, $request->user()->id)
        );
    }

    public function bestAnswer(Request $request, string $slug, string $messageId): JsonResponse
    {
        $room = Room::where('slug', $slug)->firstOrFail();

        return response()->json(
            $this->markService->toggleBestAnswer($room->id, $messageId, $request->user()->id)
        );
    }
}
