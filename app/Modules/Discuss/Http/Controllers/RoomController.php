<?php

namespace App\Modules\Discuss\Http\Controllers;

use App\Modules\Discuss\Data\CreateRoomData;
use App\Modules\Discuss\Http\Requests\CreateRoomRequest;
use App\Modules\Discuss\Http\Requests\UpdateRoomSettingsRequest;
use App\Modules\Discuss\Http\Resources\RoomResource;
use App\Modules\Discuss\Models\Member;
use App\Modules\Discuss\Models\Room;
use App\Modules\Discuss\Services\MemberService;
use App\Modules\Discuss\Services\RoomService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Validation\ValidationException;

class RoomController
{
    public function __construct(
        private readonly RoomService $roomService,
        private readonly MemberService $memberService,
    ) {}

    public function index(Request $request): array
    {
        // Only show public rooms + rooms the user is a member of.
        // Private/DM rooms must not leak to non-members.
        $userId = $request->user()->id;
        $rooms = Room::active()
            ->where(function ($q) use ($userId) {
                $q->where('type', 'public')
                    ->orWhereIn('id', function ($sub) use ($userId) {
                        $sub->select('room_id')
                            ->from('discuss_members')
                            ->where('user_id', $userId)
                            ->where('is_banned', false);
                    });
            })
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        $data = collect($rooms->items())->map(function ($room) {
            $lastMessage = $this->roomService->getLastMessage($room->id);

            return [
                'id' => $room->id,
                'name' => $room->name,
                'slug' => $room->slug,
                'cover_url' => $this->roomService->freshCover($room),
                'type' => $room->type->value,
                'context_type' => $room->context_type,
                'context_id' => $room->context_id,
                'member_count' => Member::where('room_id', $room->id)->count(),
                'last_message' => $lastMessage,
                'created_at' => $room->created_at,
            ];
        });

        return [
            'data' => $data->values()->all(),
            'meta' => [
                'current_page' => $rooms->currentPage(),
                'last_page' => $rooms->lastPage(),
                'per_page' => $rooms->perPage(),
                'total' => $rooms->total(),
            ],
        ];
    }

    public function store(CreateRoomRequest $request): JsonResponse
    {
        $data = new CreateRoomData(
            name: $request->input('name'),
            slug: $request->input('slug'),
            description: $request->input('description'),
            coverUrl: $request->input('cover_url'),
            type: $request->input('type', 'public'),
            ownerUserId: $request->user()->id,
            contextType: $request->input('context_type'),
            contextId: $request->input('context_id'),
            settings: $request->input('settings', []),
        );

        $room = $this->roomService->create($data);
        $room->cover_url = $this->roomService->freshCover($room);

        return response()->json([
            'room' => new RoomResource($room),
        ], 201);
    }

    public function show(Request $request, string $slug): RoomResource
    {
        $room = Room::where('slug', $slug)->firstOrFail();

        if ($room->type->value !== 'public') {
            abort_unless($this->memberService->isMember($room->id, $request->user()->id), 403);
        }

        $room->cover_url = $this->roomService->freshCover($room);
        return new RoomResource($room);
    }

    public function update(UpdateRoomSettingsRequest $request, string $slug): JsonResponse
    {
        $room = Room::where('slug', $slug)->firstOrFail();

        $this->assertCanManageRoom($room, $request->user()->id);

        $room->update($request->only([
            'name', 'description', 'cover_url', 'type', 'is_active', 'settings',
        ]));

        $room = $room->fresh();
        $room->cover_url = $this->roomService->freshCover($room);

        return response()->json([
            'room' => new RoomResource($room),
        ]);
    }

    public function destroy(Request $request, string $slug): JsonResponse
    {
        $room = Room::where('slug', $slug)->firstOrFail();

        $this->assertCanManageRoom($room, $request->user()->id);

        $this->roomService->archive($room);

        return response()->json(['message' => 'Room archived.']);
    }

    /**
     * Generate (or rotate) the room's invite link. Only a moderator/admin
     * of the room may do this — anyone with the link can join an
     * invite_only room, so issuing it is a privileged action.
     */
    public function generateInvite(Request $request, string $slug): JsonResponse
    {
        $room = Room::where('slug', $slug)->firstOrFail();

        $this->assertCanManageRoom($room, $request->user()->id);

        $token = $this->roomService->generateInviteLink($room);

        return response()->json([
            'invite_token' => $token,
            'invite_url' => url("/discuss/invite/{$token}"),
        ]);
    }

    /**
     * Ensure the user is a moderator or admin of the room.
     * Used by update, destroy, and generateInvite.
     */
    private function assertCanManageRoom(Room $room, string $userId): void
    {
        $role = $this->memberService->roleOf($room->id, $userId);

        if (! in_array($role, ['moderator', 'admin'], true)) {
            throw ValidationException::withMessages([
                'room' => ['You do not have permission to manage this room.'],
            ]);
        }
    }
}
