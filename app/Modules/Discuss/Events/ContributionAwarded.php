<?php

namespace App\Modules\Discuss\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Notifikasi personal "+N CP" ke user yang mendapat poin. ShouldBroadcastNow
 * (bukan antrian) karena payload kecil dan toast harus muncul seketika,
 * tidak menunggu queue worker.
 */
class ContributionAwarded implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $userId,
        public readonly ?string $roomId,
        public readonly string $source,
        public readonly int $amount,
        public readonly int $baseAmount,
        public readonly int $multiplierPct,
        public readonly ?string $eventName,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        // Channel yang sama dengan MemberRankUpgraded; otorisasi di
        // Channels/RoomChannel.php ('user.{userId}').
        return new PrivateChannel('user.' . $this->userId);
    }

    public function broadcastWith(): array
    {
        return [
            'user_id' => $this->userId,
            'room_id' => $this->roomId,
            'source' => $this->source,
            'amount' => $this->amount,
            'base_amount' => $this->baseAmount,
            'multiplier' => $this->multiplierPct / 100,
            'event_name' => $this->eventName,
        ];
    }
}
