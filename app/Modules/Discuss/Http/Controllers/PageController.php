<?php

namespace App\Modules\Discuss\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Modules\Discuss\Http\Resources\MessageResource;
use App\Modules\Discuss\Services\EmoteService;
use App\Modules\Discuss\Services\MemberService;
use App\Modules\Discuss\Services\MessageService;
use App\Modules\Discuss\Services\NotificationService;
use App\Modules\Discuss\Services\PinnedMessageService;
use App\Modules\Discuss\Services\RoomService;
use App\Modules\Auth\Models\User;
use App\Modules\Auth\Enums\UserRole;
use App\Modules\Discuss\Models\Member;
use Illuminate\Validation\ValidationException;

class PageController
{
    public function __construct(
        private RoomService $roomService,
        private MessageService $messageService,
        private MemberService $memberService,
        private EmoteService $emoteService,
        private NotificationService $notificationService,
        private PinnedMessageService $pinnedMessageService,
    ) {}

    public function index()
    {
        return Inertia::render('Discuss/Index', [
            'rooms' => $this->roomService->roomsForUser(auth()->id()),
            'directChats' => $this->roomService->getDirectChatsForUser(auth()->id())
        ]);
    }

    /** Leaderboard + achievement CP. Datanya diambil halaman lewat API. */
    public function leaderboard()
    {
        $user = auth()->user();

        return Inertia::render('Discuss/Leaderboard', [
            'isAdmin' => $user && $user->role === UserRole::Admin,
        ]);
    }

    /** Story: pemberitahuan dari admin. Admin melihat panel kelola di halaman yang sama. */
    public function story()
    {
        $user = auth()->user();

        return Inertia::render('Discuss/Story', [
            'isAdmin' => $user && $user->role === UserRole::Admin,
        ]);
    }

    /** Pengaturan & event CP — hanya admin platform (users.role = admin). */
    public function cpAdmin()
    {
        $user = auth()->user();

        abort_unless($user && $user->role === UserRole::Admin, 403);

        return Inertia::render('Discuss/CpAdmin');
    }

    /** Antrian laporan — hanya moderator/admin room. */
    public function reports(string $slug)
    {
        $room = $this->roomService->findBySlug($slug);

        abort_unless($room, 404);

        $member = Member::where('room_id', $room->id)->where('user_id', auth()->id())->first();

        abort_unless(
            $member && ! $member->is_banned && in_array($member->role->value, ['moderator', 'admin'], true),
            403,
        );

        return Inertia::render('Discuss/Reports', [
            'room' => ['slug' => $room->slug, 'name' => $room->name],
        ]);
    }

    public function room(Request $request, string $slug)
    {
        $userId = auth()->id();

        $room = $this->roomService->findBySlug($slug);

        if (!$room && str_starts_with($slug, 'comic-')) {
            // Only admin users can create comic discussion rooms
            $user = User::find($userId);
            if (!$user || $user->role !== UserRole::Admin) {
                throw ValidationException::withMessages([
                    'room' => ['Only admin users can create comic discussion rooms.'],
                ]);
            }

            $comicSlug = substr($slug, 6);
            $title = $request->query('title', 'Comic Discuss');
            $room = $this->roomService->findOrCreateForComic($comicSlug, $title, $userId);
            return redirect()->route('discuss.room', ['slug' => $room->slug]);
        }

        abort_unless($room, 404);

        // Private rooms (including 1:1 direct chats) are only visible to members.
        // Public rooms remain open to any authenticated user, matching the
        // existing "browse & join" flow.
        if ($room->type->value !== 'public') {
            abort_unless($this->memberService->isMember($room->id, $userId), 403);
        }

        $room->cover_url = $this->roomService->freshCover($room);

        return Inertia::render('Discuss/Room', [
            'room'          => $room,
            'directRecipient' => $this->roomService->getDirectRecipient($room, $userId),
            'messages'      => MessageResource::collection(
                $this->messageService->paginate($room->id, limit: 50)
            )->toArray($request),
            'members'       => $this->memberService->listForRoom($room->id),
            'emotes'        => $this->emoteService->availableFor($room->id),
            'currentUserId' => $userId,
            'pinnedMessages' => $this->pinnedMessageService->getPinnedAll($room),
        ]);
    }

    /**
     * "Discuss About" page: room avatar/name header with Member/Media tabs.
     * Same membership guard as room() — private rooms (including direct
     * chats) are only visible to members.
     */
    public function about(string $slug)
    {
        $userId = auth()->id();

        $room = $this->roomService->findBySlug($slug);

        abort_unless($room, 404);

        if ($room->type->value !== 'public') {
            abort_unless($this->memberService->isMember($room->id, $userId), 403);
        }

        $room->cover_url = $this->roomService->freshCover($room);

        return Inertia::render('Discuss/About', [
            'room'          => $room,
            'directRecipient' => $this->roomService->getDirectRecipient($room, $userId),
            'members'       => $this->memberService->listForRoom($room->id),
            'currentUserId' => $userId,
        ]);
    }

    /**
     * Landing route for an invite link (e.g. /discuss/invite/{token}).
     * Unlike room()/about(), this is reachable by a non-member — resolving
     * the room by token, joining the current user immediately (validated
     * again inside MemberService::join(), not just trusted from the URL),
     * then redirecting into the room. Bad/expired tokens 404.
     */
    public function joinByInvite(Request $request, string $token)
    {
        $userId = auth()->id();

        $room = $this->roomService->findByInviteToken($token);

        abort_unless($room, 404);

        try {
            $this->memberService->join($room->id, $userId, $token);
        } catch (ValidationException $e) {
            // Already banned, or some other join-time rule failed — surface
            // it as a plain 403 rather than a validation error page, since
            // there's no form here to attach field errors to.
            abort(403, collect($e->errors())->flatten()->first() ?? 'Could not join this room.');
        }

        return redirect()->route('discuss.room', ['slug' => $room->slug]);
    }
}
