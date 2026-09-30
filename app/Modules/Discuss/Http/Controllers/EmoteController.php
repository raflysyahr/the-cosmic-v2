<?php

namespace App\Modules\Discuss\Http\Controllers;

use App\Modules\Auth\Models\User;
use App\Modules\Discuss\Http\Requests\UploadEmoteRequest;
use App\Modules\Discuss\Models\Emote;
use App\Modules\Discuss\Services\EmoteService;
use App\Modules\Discuss\Services\MemberService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class EmoteController
{
    public function __construct(
        private readonly EmoteService $emoteService,
        private readonly MemberService $memberService,
    ) {}

    public function index(Request $request): Collection
    {
        $roomId = $request->input('room_id');

        if ($roomId) {
            return $this->emoteService->availableFor($roomId);
        }

        return Emote::active()->select('id', 'code', 'image_url', 'unicode')->get();
    }

    /**
     * Room-scoped emotes require moderator/admin of that room. Global
     * emotes (room_id null) are a platform-wide asset, so they require the
     * uploader's site-wide role (App\Modules\Auth\Models\User::role) to be
     * admin — a cross-module read via select(), not an Eloquent relation,
     * per AGENTS.md.
     */
    private function assertCanManageEmote(?string $roomId, string $userId): void
    {
        if ($roomId) {
            $role = $this->memberService->roleOf($roomId, $userId);

            if (! in_array($role, ['moderator', 'admin'], true)) {
                throw ValidationException::withMessages([
                    'room' => ['You do not have permission to manage emotes in this room.'],
                ]);
            }

            return;
        }

        $user = User::select('id', 'role')->find($userId);

        if (! $user || $user->role->value !== 'admin') {
            throw ValidationException::withMessages([
                'room' => ['Only site admins can manage global emotes.'],
            ]);
        }
    }

    public function store(UploadEmoteRequest $request): JsonResponse
    {
        $roomId = $request->input('room_id');

        $this->assertCanManageEmote($roomId, $request->user()->id);

        $emote = $this->emoteService->upload(
            file: $request->file('image'),
            code: $request->input('code'),
            name: $request->input('name'),
            roomId: $roomId,
            userId: $request->user()->id,
        );

        return response()->json(['emote' => $emote], 201);
    }

    public function destroy(Request $request, string $emoteId): JsonResponse
    {
        $emote = Emote::findOrFail($emoteId);

        $this->assertCanManageEmote($emote->room_id, $request->user()->id);

        $this->emoteService->deactivate($emote);

        return response()->json(['message' => 'Emote deactivated.']);
    }
}
