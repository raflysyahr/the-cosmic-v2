<?php

namespace App\Modules\Discuss\Events;

use App\Modules\Auth\Models\User;
use App\Modules\Discuss\Models\Member;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MemberLeft implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public readonly string $roomId;

    public readonly string $userId;

    /**
     * Hanya nilai skalar yang disimpan, BUKAN model Member. Barisnya sudah
     * dihapus saat event dikirim; kalau model ikut diserialisasi
     * (SerializesModels), proses queue/broadcast gagal me-restore-nya
     * (ModelNotFoundException).
     */
    public function __construct(Member $member)
    {
        $this->roomId = (string) $member->room_id;
        $this->userId = (string) $member->user_id;
    }

    public function broadcastOn(): PresenceChannel
    {
        return new PresenceChannel('room.' . $this->roomId);
    }

    public function broadcastWith(): array
    {
        // Baris member sudah dihapus dari DB (lihat MemberService::leave()),
        // tapi user_id disimpan di event, jadi query User berikut tetap valid.
        $user = User::select('display_name')->where('id', $this->userId)->first();

        return [
            'user_id' => $this->userId,
            'room_id' => $this->roomId,
            'display_name' => $user?->display_name ?? 'Someone',
        ];
    }
}
