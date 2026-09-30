<?php

namespace App\Modules\Discuss\Http\Controllers;

use App\Modules\Auth\Models\User;
use App\Modules\Discuss\Services\RoomService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;

class DirectController
{
    public function __construct(
        private readonly RoomService $roomService,
    ) {}

    /**
     * List all direct chats for the current user.
     */
    public function index(): Response
    {
        return Inertia::render('Discuss/DirectList', [
            'directChats' => $this->roomService->getDirectChatsForUser(auth()->id()),
        ]);
    }

    /**
     * Open (or create) a 1:1 direct chat with the given user, then redirect
     * into the shared Room page.
     */
    public function open(Request $request, string $username): RedirectResponse
    {
        $targetUser = User::where('username', $username)
            ->orWhere('id', $username)
            ->firstOrFail();

        if ($targetUser->id === auth()->id()) {
            abort(400, 'Cannot start a direct chat with yourself.');
        }

        $room = $this->roomService->findOrCreateDirectChat(auth()->id(), $targetUser->id);

        return redirect()->route('discuss.room', ['slug' => $room->slug]);
    }
}
