<?php

namespace App\Modules\Discuss\Http\Resources;

use App\Modules\Auth\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $actor = $this->actor_user_id
            ? User::select('id', 'display_name', 'avatar_url')
                ->where('id', $this->actor_user_id)
                ->first()
            : null;

        return [
            'id' => $this->id,
            'type' => $this->type,
            'actor' => $actor ? [
                'id' => $actor->id,
                'display_name' => $actor->display_name,
                'avatar_url' => $actor->avatar_url,
            ] : null,
            'payload' => $this->payload,
            'is_read' => $this->is_read,
            'created_at' => $this->created_at,
        ];
    }
}
