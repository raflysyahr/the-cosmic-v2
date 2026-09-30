<?php

namespace App\Modules\Discuss\Http\Controllers;

use App\Modules\Discuss\Models\Room;
use App\Modules\Discuss\Services\PinnedMessageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PinnedMessageController
{
    public function __construct(
        private readonly PinnedMessageService $pinnedMessageService,
    ) {}

    public function store(Request $request, string $slug): JsonResponse
    {
        $request->validate(['message_id' => ['required', 'string']]);

        $room = Room::where('slug', $slug)->firstOrFail();

        $pinned = $this->pinnedMessageService->pin(
            $room,
            $request->input('message_id'),
            $request->user()->id,
        );

        return response()->json(['pinned' => $pinned]);
    }

    public function destroy(Request $request, string $slug): JsonResponse
    {
        $request->validate(['message_id' => ['required', 'string']]);

        $room = Room::where('slug', $slug)->firstOrFail();

        $pinned = $this->pinnedMessageService->unpin(
            $room,
            $request->input('message_id'),
            $request->user()->id,
        );

        return response()->json(['pinned' => $pinned]);
    }
}
