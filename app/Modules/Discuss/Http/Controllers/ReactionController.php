<?php

namespace App\Modules\Discuss\Http\Controllers;

use App\Modules\Discuss\Models\Room;
use App\Modules\Discuss\Services\ReactionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReactionController
{
    public function __construct(
        private readonly ReactionService $reactionService,
    ) {}

    public function store(Request $request, string $slug, string $messageId): JsonResponse
    {
        $request->validate(['emote_id' => ['required', 'string']]);

        $room = Room::where('slug', $slug)->firstOrFail();

        $this->reactionService->toggle(
            roomId: $room->id,
            messageId: $messageId,
            userId: $request->user()->id,
            emoteId: $request->input('emote_id'),
        );

        return response()->json(['message' => 'Reaction toggled.']);
    }
}
