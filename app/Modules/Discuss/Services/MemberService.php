<?php

namespace App\Modules\Discuss\Services;

use App\Modules\Discuss\Enums\MemberRole;
use App\Modules\Discuss\Enums\RoomType;
use App\Modules\Discuss\Events\MemberBanned;
use App\Modules\Discuss\Events\MemberJoined;
use App\Modules\Discuss\Events\MemberLeft;
use App\Modules\Discuss\Models\Member;
use App\Modules\Discuss\Models\Rank;
use App\Modules\Discuss\Models\Room;
use App\Modules\Auth\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class MemberService
{
    /**
     * Sort weight used to order members by role without relying on a
     * database-specific function like MySQL's FIELD(), which is not
     * portable to SQLite (used in tests) or Postgres.
     */
    private const ROLE_ORDER = [
        'admin' => 0,
        'moderator' => 1,
        'member' => 2,
    ];

    /**
     * Join a room. Public rooms can always be joined this way. Invite-only
     * rooms additionally require a valid, matching invite token (see
     * RoomService::generateInviteLink()/findByInviteToken()). Private rooms
     * have no self-service join path at all.
     */
    public function join(string $roomId, string $userId, ?string $inviteToken = null): Member
    {
        $room = Room::findOrFail($roomId);

        $canJoin = $room->type === RoomType::Public
            || ($room->type === RoomType::InviteOnly
                && $inviteToken !== null
                && $inviteToken !== ''
                && ($room->settings['invite_link'] ?? null) === $inviteToken);

        if (! $canJoin) {
            throw ValidationException::withMessages([
                'room' => [
                    $room->type === RoomType::Private
                        ? 'This room is private. Join by invite only.'
                        : 'This room is invite-only. You need a valid invite link to join.',
                ],
            ]);
        }

        $existing = Member::where('room_id', $roomId)
            ->where('user_id', $userId)
            ->first();

        if ($existing && $existing->is_banned) {
            throw ValidationException::withMessages([
                'room' => ['You are banned from this room.'],
            ]);
        }

        if ($existing) {
            return $existing;
        }

        $member = Member::create([
            'room_id' => $roomId,
            'user_id' => $userId,
            'role' => 'member',
            'xp_points' => 0,
            'is_banned' => false,
            'joined_at' => now(),
            'last_read_at' => now(),
        ]);

        event(new MemberJoined($member));

        return $member;
    }

    public function leave(string $roomId, string $userId): void
    {
        $member = Member::where('room_id', $roomId)
            ->where('user_id', $userId)
            ->firstOrFail();

        $member->delete();

        event(new MemberLeft($member));
    }

    public function kick(Member $member, string $byUserId): void
    {
        $this->assertCanModerate($member, $byUserId);

        $member->delete();
    }

    public function mute(Member $member, int $minutes, string $byUserId): void
    {
        $this->assertCanModerate($member, $byUserId);

        $member->update([
            'muted_until' => now()->addMinutes($minutes),
        ]);
    }

    public function ban(Member $member, string $byUserId): void
    {
        $this->assertCanModerate($member, $byUserId);

        $member->update([
            'is_banned' => true,
            'muted_until' => null,
        ]);

        event(new MemberBanned($member));
    }

    /**
     * Ensure $byUserId is allowed to moderate (kick/mute/ban) $target.
     *
     * Rules:
     * - The actor must be a moderator or admin in the same room.
     * - Nobody can moderate themselves.
     * - A moderator cannot moderate another moderator or an admin (admin-only).
     */
    public function assertCanModerate(Member $target, string $byUserId): void
    {
        if ((string) $target->user_id === (string) $byUserId) {
            throw ValidationException::withMessages([
                'member' => ['You cannot perform this action on yourself.'],
            ]);
        }

        $actor = Member::where('room_id', $target->room_id)
            ->where('user_id', $byUserId)
            ->first();

        if (! $actor || $actor->is_banned || ! in_array($actor->role, [MemberRole::Moderator, MemberRole::Admin], true)) {
            throw ValidationException::withMessages([
                'member' => ['You do not have permission to moderate this room.'],
            ]);
        }

        if ($actor->role === MemberRole::Moderator && in_array($target->role, [MemberRole::Moderator, MemberRole::Admin], true)) {
            throw ValidationException::withMessages([
                'member' => ['Moderators cannot moderate other moderators or admins.'],
            ]);
        }
    }

    public function listForRoom(string $roomId): Collection
    {
        $members = Member::where('room_id', $roomId)
            ->where('is_banned', false)
            ->get()
            ->sortBy(fn (Member $member) => self::ROLE_ORDER[$member->role->value] ?? 99)
            ->values();

        // `Member` intentionally has no `rank()` Eloquent relationship (see
        // AGENTS.md — no relationships even within a module), so rank info
        // must be resolved with a manual, batched query instead of relying
        // on `$member->rank`, which would silently resolve to null forever.
        $rankIds = $members->pluck('rank_id')->filter()->unique()->values();
        $ranks = $rankIds->isEmpty()
            ? collect()
            : Rank::select('id', 'name', 'label_color')
                ->whereIn('id', $rankIds)
                ->get()
                ->keyBy('id');

        return $members->map(function (Member $member) use ($ranks) {
                $user = User::select('id', 'username', 'display_name', 'avatar_url')
                    ->where('id', $member->user_id)
                    ->first();

                $rank = $member->rank_id ? $ranks->get($member->rank_id) : null;

                return [
                    'userId'      => $member->user_id,
                    'username'    => $user?->username,
                    'displayName' => $user?->display_name ?? 'Unknown',
                    'avatarUrl'   => $user?->avatar_url,
                    'role'        => $member->role,
                    'xpPoints'    => $member->xp_points,
                    'rank'        => $rank ? [
                        'name'  => $rank->name,
                        'color' => $rank->label_color,
                    ] : null,
                    'isOnline'    => false,
                    // ISO 8601 string, or null if not currently muted. Frontend
                    // compares against current time to decide whether the mute
                    // is still active (a past timestamp just hasn't been cleared
                    // from the DB yet, since we don't run a cleanup job for it).
                    'mutedUntil'  => $member->muted_until?->toIso8601String(),
                ];
            });
    }

    public function isMember(string $roomId, string $userId): bool
    {
        return Member::where('room_id', $roomId)
            ->where('user_id', $userId)
            ->where('is_banned', false)
            ->exists();
    }

    public function roleOf(string $roomId, string $userId): ?string
    {
        $member = Member::where('room_id', $roomId)
            ->where('user_id', $userId)
            ->first();

        return $member?->role?->value;
    }
}
