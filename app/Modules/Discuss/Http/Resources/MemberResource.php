<?php

namespace App\Modules\Discuss\Http\Resources;

use App\Modules\Auth\Models\User;
use App\Modules\Discuss\Models\Rank;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MemberResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = User::select('id', 'display_name', 'avatar_url')
            ->where('id', $this->user_id)
            ->first();

        $rank = $this->rank_id
            ? Rank::find($this->rank_id)
            : null;

        return [
            'id' => $this->id,
            'user' => $user ? [
                'id' => $user->id,
                'display_name' => $user->display_name,
                'avatar_url' => $user->avatar_url,
            ] : null,
            'role' => $this->role->value,
            'xp_points' => $this->xp_points,
            'rank' => $rank ? [
                'id' => $rank->id,
                'name' => $rank->name,
                'label_color' => $rank->label_color,
                'icon_url' => $rank->icon_url,
            ] : null,
            'joined_at' => $this->joined_at,
        ];
    }
}
