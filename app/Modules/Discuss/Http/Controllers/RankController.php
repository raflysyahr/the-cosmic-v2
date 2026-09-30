<?php

namespace App\Modules\Discuss\Http\Controllers;

use App\Modules\Discuss\Http\Requests\CreateRankRequest;
use App\Modules\Discuss\Models\Rank;
use App\Modules\Discuss\Models\Room;
use App\Modules\Discuss\Services\MemberService;
use App\Modules\Discuss\Services\RankService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class RankController
{
    public function __construct(
        private readonly RankService $rankService,
        private readonly MemberService $memberService,
    ) {}

    public function index(string $slug): JsonResponse
    {
        $room = Room::where('slug', $slug)->firstOrFail();

        $ranks = $this->rankService->ranksForRoom($room->id);

        return response()->json(['ranks' => $ranks]);
    }

    /**
     * Only a moderator or admin of the room may manage its ranks.
     */
    private function assertCanManageRanks(Room $room, string $userId): void
    {
        $role = $this->memberService->roleOf($room->id, $userId);

        if (! in_array($role, ['moderator', 'admin'], true)) {
            throw ValidationException::withMessages([
                'room' => ['You do not have permission to manage ranks in this room.'],
            ]);
        }
    }

    public function store(CreateRankRequest $request, string $slug): JsonResponse
    {
        $room = Room::where('slug', $slug)->firstOrFail();

        $this->assertCanManageRanks($room, $request->user()->id);

        $rank = Rank::create([
            'room_id' => $room->id,
            'name' => $request->input('name'),
            'label_color' => $request->input('label_color'),
            'icon_url' => $request->input('icon_url'),
            'min_xp' => $request->input('min_xp'),
            'order' => $request->input('order'),
            'perks' => $request->input('perks', []),
        ]);

        return response()->json(['rank' => $rank], 201);
    }

    /**
     * Resolve a rank that must belong to the room identified by the URL's
     * {slug}. The rank id alone isn't trustworthy scoping — without this,
     * the {slug} segment would be decorative and any rank id could be
     * updated/deleted regardless of which room's URL was used.
     */
    private function resolveRankForRoom(Room $room, string $rankId): Rank
    {
        return Rank::where('id', $rankId)
            ->where('room_id', $room->id)
            ->firstOrFail();
    }

    public function update(Request $request, string $slug, string $rankId): JsonResponse
    {
        $request->validate([
            'name' => ['sometimes', 'string', 'max:50'],
            'label_color' => ['sometimes', 'string', 'max:7'],
            'icon_url' => ['nullable', 'url'],
            'min_xp' => ['sometimes', 'integer', 'min:0'],
            'order' => ['sometimes', 'integer', 'min:1'],
            'perks' => ['nullable', 'array'],
        ]);

        $room = Room::where('slug', $slug)->firstOrFail();
        $this->assertCanManageRanks($room, $request->user()->id);

        $rank = $this->resolveRankForRoom($room, $rankId);
        $rank->update($request->only([
            'name', 'label_color', 'icon_url', 'min_xp', 'order', 'perks',
        ]));

        return response()->json(['rank' => $rank->fresh()]);
    }

    public function destroy(Request $request, string $slug, string $rankId): JsonResponse
    {
        $room = Room::where('slug', $slug)->firstOrFail();
        $this->assertCanManageRanks($room, $request->user()->id);

        $rank = $this->resolveRankForRoom($room, $rankId);
        $rank->delete();

        return response()->json(['message' => 'Rank deleted.']);
    }
}
