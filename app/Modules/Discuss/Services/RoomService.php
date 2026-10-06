<?php

namespace App\Modules\Discuss\Services;

use App\Modules\Auth\Models\User;
use App\Modules\Discuss\Data\CreateRoomData;
use App\Modules\Discuss\Enums\RoomType;
use App\Modules\Discuss\Models\Member;
use App\Modules\Discuss\Models\Message;
use App\Modules\Discuss\Models\Room;
use App\Services\KomikcastService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class RoomService
{
    public function __construct(
        private readonly KomikcastService $komikcast,
    ) {}
    public function create(CreateRoomData $data): Room
    {
        $room = Room::create([
            'slug' => $data->slug,
            'name' => $data->name,
            'description' => $data->description,
            'cover_url' => $data->coverUrl,
            'type' => $data->type,
            'owner_user_id' => $data->ownerUserId,
            'context_type' => $data->contextType,
            'context_id' => $data->contextId,
            'is_active' => true,
            'settings' => array_merge([
                'slow_mode_seconds' => 0,
                'max_members' => 500,
                'xp_per_message' => 5,
            ], $data->settings),
        ]);

        Member::create([
            'room_id' => $room->id,
            'user_id' => $data->ownerUserId,
            'role' => 'admin',
            'xp_points' => 0,
            'is_banned' => false,
            'joined_at' => now(),
            'last_read_at' => now(),
        ]);

        return $room;
    }

    public function archive(Room $room): void
    {
        $room->update(['is_active' => false]);
    }

    /**
     * Generate (or rotate) the room's invite token, stored in settings.
     * Any previously-shared invite link stops working once rotated.
     */
    public function generateInviteLink(Room $room): string
    {
        $link = Str::random(16);

        $room->update([
            'settings' => array_merge($room->settings, ['invite_link' => $link]),
        ]);

        return $link;
    }

    /**
     * Resolve the room a given invite token belongs to, if any (and only if
     * the room is still active). Used to validate/join via an invite link.
     */
    public function findByInviteToken(string $token): ?Room
    {
        return Room::where('is_active', true)
            ->where('settings->invite_link', $token)
            ->first();
    }

    public function findByContext(string $type, string $id): ?Room
    {
        return Room::where('context_type', $type)
            ->where('context_id', $id)
            ->first();
    }

    public function publicRooms(): Collection
    {
        return Room::where('is_active', true)
            ->where('type', 'public')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function roomsForUser(?string $userId): array
    {
        $rooms = Room::where('is_active', true)
            ->where(function ($q) use ($userId) {
                $q->where('type', 'public');
                if ($userId) {
                    $q->orWhereIn('id', function ($sub) use ($userId) {
                        $sub->select('room_id')
                            ->from('discuss_members')
                            ->where('user_id', $userId)
                            ->where('is_banned', false);
                    });
                }
            })
            ->orderBy('created_at', 'desc')
            ->get();

        return $rooms->map(fn($room) => [
                'id' => $room->id,
                'name' => $room->name,
                'slug' => $room->slug,
                'cover_url' => $this->freshCover($room),
                'type' => $room->type->value,
                'context_type' => $room->context_type,
                'context_id' => $room->context_id,
                'member_count' => Member::where('room_id', $room->id)->count(),
                'last_message' => $this->getLastMessage($room->id),
                'created_at' => $room->created_at,
            ])
            ->values()
            ->all();
    }

    /**
     * Generate a deterministic, order-independent context_id for a 1:1 direct chat
     * between two users. ULIDs sort lexicographically, so a plain sort() is safe.
     */
    private function generateDirectContextId(string $user1Id, string $user2Id): string
    {
        $ids = [$user1Id, $user2Id];
        sort($ids);

        return implode('_', $ids);
    }

    /**
     * Find the existing direct chat room between two users WITHOUT creating one.
     */
    public function findDirectChat(string $user1Id, string $user2Id): ?Room
    {
        return $this->findByContext('direct', $this->generateDirectContextId($user1Id, $user2Id));
    }

    /**
     * Find the existing direct chat room between two users, or create a new one.
     * The room is created as Private with context_type=direct so it never shows
     * up in public listings, and both users are added as members.
     */
    public function findOrCreateDirectChat(string $user1Id, string $user2Id): Room
    {
        $contextId = $this->generateDirectContextId($user1Id, $user2Id);

        $room = $this->findByContext('direct', $contextId);

        if ($room) {
            return $room;
        }

        $user1 = User::select('id', 'display_name')->findOrFail($user1Id);
        $user2 = User::select('id', 'display_name')->findOrFail($user2Id);

        $data = new CreateRoomData(
            name: $user1->display_name . ' & ' . $user2->display_name,
            slug: 'direct-' . Str::random(12),
            description: null,
            coverUrl: null,
            type: RoomType::Private->value,
            ownerUserId: $user1Id,
            contextType: 'direct',
            contextId: $contextId,
            settings: [
                'is_direct' => true,
                'max_members' => 2,
            ],
        );

        // create() already adds $user1Id as an admin member; add the second participant.
        $room = $this->create($data);

        Member::create([
            'room_id' => $room->id,
            'user_id' => $user2Id,
            'role' => 'member',
            'xp_points' => 0,
            'is_banned' => false,
            'joined_at' => now(),
            'last_read_at' => now(),
        ]);

        return $room;
    }

    /**
     * List all direct-chat rooms the given user belongs to, with the other
     * participant's info resolved via a cross-module query (per AGENTS.md).
     */
    public function getDirectChatsForUser(string $userId): array
    {
        $rooms = Room::where('context_type', 'direct')
            ->where('is_active', true)
            ->whereIn('id', function ($query) use ($userId) {
                $query->select('room_id')
                    ->from('discuss_members')
                    ->where('user_id', $userId)
                    ->where('is_banned', false);
            })
            ->orderBy('created_at', 'desc')
            ->get();

        return $rooms->map(function (Room $room) use ($userId) {
            $otherMember = Member::where('room_id', $room->id)
                ->where('user_id', '!=', $userId)
                ->first();

            $otherUser = $otherMember
                ? User::select('id', 'display_name', 'avatar_url')
                    ->where('id', $otherMember->user_id)
                    ->first()
                : null;

            return [
                'id' => $room->id,
                'slug' => $room->slug,
                'context_type' => $room->context_type,
                'other_user' => $otherUser ? [
                    'id' => $otherUser->id,
                    'display_name' => $otherUser->display_name,
                    'avatar_url' => $otherUser->avatar_url,
                ] : null,
                'last_message' => $this->getLastMessage($room->id),
                'created_at' => $room->created_at,
            ];
        })->values()->all();
    }

    /**
     * Resolve the "other participant" for a 1:1 direct chat room, relative to
     * the given current user. Returns null if the room is not a direct chat
     * or the other participant can't be resolved (e.g. they left the room).
     */
    public function getDirectRecipient(Room $room, string $currentUserId): ?array
    {
        if ($room->context_type !== 'direct') {
            return null;
        }

        $otherMember = Member::where('room_id', $room->id)
            ->where('user_id', '!=', $currentUserId)
            ->first();

        if (! $otherMember) {
            return null;
        }

        $otherUser = User::select('id', 'display_name', 'avatar_url')
            ->where('id', $otherMember->user_id)
            ->first();

        if (! $otherUser) {
            return null;
        }

        return [
            'id' => $otherUser->id,
            'display_name' => $otherUser->display_name,
            'avatar_url' => $otherUser->avatar_url,
        ];
    }

    public function getLastMessage(string $roomId): ?array
    {
        $message = Message::where('room_id', $roomId)
            ->where('is_deleted', false)
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$message) return null;

        $user = User::select('id', 'display_name')
            ->where('id', $message->user_id)
            ->first();

        return [
            'body' => $message->body,
            'created_at' => $message->created_at,
            'user' => $user ? [
                'id' => $user->id,
                'display_name' => $user->display_name,
            ] : null,
        ];
    }

    public function findBySlug(string $slug): ?Room
    {
        return Room::where('slug', $slug)
            ->where('is_active', true)
            ->first();
    }

    public function findOrCreateForComic(string $comicSlug, string $title, string $userId): Room
    {
        $roomSlug = 'comic-' . $comicSlug;

        $room = Room::where('slug', $roomSlug)->first();
        if ($room) {
            return $room;
        }

        $data = new CreateRoomData(
            name: $title,
            slug: $roomSlug,
            description: null,
            coverUrl: null,
            type: RoomType::Public->value,
            ownerUserId: $userId,
            contextType: 'comic',
            contextId: $comicSlug,
            settings: [],
        );

        return $this->create($data);
    }

    public function freshCover(Room $room): ?string
    {
        if ($room->context_type !== 'comic' || !$room->context_id) {
            return $room->cover_url;
        }

        try {
            $detail = $this->komikcast->getDetail($room->context_id);
            return $detail['data']['cover'] ?? $room->cover_url;
        } catch (\Throwable) {
            return $room->cover_url;
        }
    }
}
