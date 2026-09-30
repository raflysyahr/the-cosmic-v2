<?php

namespace App\Modules\Discuss\Http\Resources;

use App\Modules\Auth\Models\User;
use App\Modules\Discuss\Models\Member;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoomResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $owner = User::select('id', 'display_name', 'avatar_url')
            ->where('id', $this->owner_user_id)
            ->first();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'cover_url' => $this->cover_url,
            'type' => $this->type->value,
            'owner' => $owner ? [
                'id' => $owner->id,
                'display_name' => $owner->display_name,
                'avatar_url' => $owner->avatar_url,
            ] : null,
            'settings' => $this->settings,
            'is_active' => $this->is_active,
            'member_count' => Member::where('room_id', $this->id)->count(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
