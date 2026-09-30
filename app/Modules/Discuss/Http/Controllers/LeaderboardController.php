<?php

namespace App\Modules\Discuss\Http\Controllers;

use App\Modules\Discuss\Models\Room;
use App\Modules\Discuss\Services\LeaderboardService;
use App\Modules\Discuss\Services\MemberService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeaderboardController
{
    public function __construct(
        private readonly LeaderboardService $leaderboard,
        private readonly MemberService $memberService,
    ) {}

    public function global(Request $request): JsonResponse
    {
        return response()->json($this->leaderboard->top(
            $this->period($request),
            null,
            50,
            (string) $request->user()->id,
        ));
    }

    public function room(Request $request, string $slug): JsonResponse
    {
        $room = Room::where('slug', $slug)->firstOrFail();

        // Room non-publik: hanya member yang boleh melihat papan peringkatnya.
        if ($room->type->value !== 'public') {
            abort_unless($this->memberService->isMember($room->id, $request->user()->id), 403);
        }

        return response()->json($this->leaderboard->top(
            $this->period($request),
            $room->id,
            50,
            (string) $request->user()->id,
        ));
    }

    private function period(Request $request): string
    {
        $period = (string) $request->query('period', 'weekly');

        return in_array($period, LeaderboardService::PERIODS, true) ? $period : 'weekly';
    }
}
