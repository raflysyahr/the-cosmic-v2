<?php

use Illuminate\Support\Facades\Broadcast;
use App\Modules\Discuss\Models\Member;
use App\Modules\Discuss\Models\Rank;

Broadcast::channel('room.{roomId}', function ($user, $roomId) {
    $member = Member::where('room_id', $roomId)
        ->where('user_id', $user->id)
        ->where('is_banned', false)
        ->first();

    if (!$member) return false;

    // `Member` intentionally has no `rank()` Eloquent relationship (see
    // AGENTS.md — no relationships even within a module), so rank info must
    // be resolved with a manual query. `$member->rank` would silently
    // resolve to null forever since no such relation or attribute exists.
    $rank = $member->rank_id
        ? Rank::select('name', 'label_color')->find($member->rank_id)
        : null;

    return [
        'id'           => $user->id,
        'display_name' => $user->display_name,
        'avatar_url'   => $user->avatar_url,
        'role'         => $member->role,
        'rank'         => $rank ? [
            'name'  => $rank->name,
            'color' => $rank->label_color,
        ] : null,
    ];
});

Broadcast::channel('user.{userId}', function ($user, $userId) {
    return (string) $user->id === (string) $userId;
});
