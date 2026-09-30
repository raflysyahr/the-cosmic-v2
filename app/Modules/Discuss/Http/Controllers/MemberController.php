<?php

namespace App\Modules\Discuss\Http\Controllers;

use App\Modules\Discuss\Models\Member;
use App\Modules\Discuss\Models\Room;
use App\Modules\Discuss\Services\MemberService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class MemberController
{
    public function __construct(
        private readonly MemberService $memberService,
    ) {}

    public function index(string $slug): Collection
    {
        $room = Room::where('slug', $slug)->firstOrFail();

        return $this->memberService->listForRoom($room->id);
    }

    public function store(Request $request, string $slug): JsonResponse
    {
        $room = Room::where('slug', $slug)->firstOrFail();

        $member = $this->memberService->join(
            $room->id,
            $request->user()->id,
            $request->input('invite_token'),
        );

        return response()->json(['message' => 'Joined room.'], 201);
    }

    public function destroy(string $slug): JsonResponse
    {
        $room = Room::where('slug', $slug)->firstOrFail();

        $this->memberService->leave($room->id, request()->user()->id);

        return response()->json(['message' => 'Left room.']);
    }

    /**
     * Resolve the target Member row for a moderation action. The route
     * parameter is the target's user_id (what the frontend has on hand),
     * not the discuss_members primary key, so we look it up via room+user.
     */
    private function resolveTargetMember(string $slug, string $userId): Member
    {
        $room = Room::where('slug', $slug)->firstOrFail();

        return Member::where('room_id', $room->id)
            ->where('user_id', $userId)
            ->firstOrFail();
    }

    public function kick(string $slug, string $userId): JsonResponse
    {
        $member = $this->resolveTargetMember($slug, $userId);

        $this->memberService->kick($member, request()->user()->id);

        return response()->json(['message' => 'Member kicked.']);
    }

    public function mute(Request $request, string $slug, string $userId): JsonResponse
    {
        $request->validate(['minutes' => ['required', 'integer', 'min:1']]);

        $member = $this->resolveTargetMember($slug, $userId);

        $this->memberService->mute($member, $request->input('minutes'), $request->user()->id);

        return response()->json(['message' => 'Member muted.']);
    }

    public function ban(string $slug, string $userId): JsonResponse
    {
        $member = $this->resolveTargetMember($slug, $userId);

        $this->memberService->ban($member, request()->user()->id);

        return response()->json(['message' => 'Member banned.']);
    }
}
