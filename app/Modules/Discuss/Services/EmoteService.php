<?php

namespace App\Modules\Discuss\Services;

use App\Modules\Discuss\Models\Emote;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class EmoteService
{
    public function upload(UploadedFile $file, string $code, string $name, ?string $roomId, string $userId): Emote
    {
        $path = $file->store('emotes', 'public');

        return Emote::create([
            'room_id' => $roomId,
            'code' => $code,
            'name' => $name,
            'image_url' => Storage::url($path),
            'is_animated' => $file->getClientOriginalExtension() === 'gif',
            'is_active' => true,
            'uploaded_by_user_id' => $userId,
        ]);
    }

    public function deactivate(Emote $emote): void
    {
        $emote->update(['is_active' => false]);
    }

    public function availableFor(string $roomId): Collection
    {
        return Emote::active()->forRoom($roomId)
            ->select('id', 'code', 'image_url', 'unicode')
            ->get();
    }
}
