<?php

namespace App\Modules\Discuss\Services;

use App\Modules\Auth\Models\User;
use App\Modules\Discuss\Models\CpLog;
use Illuminate\Support\Carbon;

/**
 * Leaderboard dihitung dari ledger discuss_cp_logs (SUM(amount) per
 * periode) — tidak ada kolom "weekly_cp" yang perlu di-reset cron.
 */
class LeaderboardService
{
    public const PERIODS = ['weekly', 'monthly', 'all'];

    /**
     * @param string|null $roomId null = global (hanya room publik; poin
     *        dari room private/invite-only/DM tidak bocor ke papan global)
     */
    public function top(string $period, ?string $roomId = null, int $limit = 50, ?string $meId = null): array
    {
        $since = $this->since($period);

        $query = CpLog::query()->selectRaw('user_id, SUM(amount) as points');

        if ($roomId !== null) {
            $query->where('room_id', $roomId);
        } else {
            $query->whereIn('room_id', function ($q) {
                $q->select('id')->from('discuss_rooms')->where('type', 'public');
            });
        }

        if ($since) {
            $query->where('created_at', '>=', $since);
        }

        $rows = $query->groupBy('user_id')
            ->havingRaw('SUM(amount) > 0')
            ->orderByDesc('points')
            ->orderBy('user_id')
            ->limit($limit)
            ->get();

        $users = $rows->isEmpty()
            ? collect()
            : User::select('id', 'username', 'display_name', 'avatar_url')
                ->whereIn('id', $rows->pluck('user_id'))
                ->get()
                ->keyBy('id');

        $entries = [];
        foreach ($rows->values() as $i => $row) {
            $user = $users->get($row->user_id);
            $entries[] = [
                'rank' => $i + 1,
                'userId' => $row->user_id,
                'username' => $user?->username,
                'displayName' => $user?->display_name ?? 'Unknown',
                'avatarUrl' => $user?->avatar_url,
                'points' => (int) $row->points,
            ];
        }

        return [
            'period' => $period,
            'entries' => $entries,
            'me' => $meId ? $this->pointsFor($meId, $since, $roomId) : null,
        ];
    }

    private function pointsFor(string $userId, ?Carbon $since, ?string $roomId): int
    {
        $query = CpLog::where('user_id', $userId);

        if ($roomId !== null) {
            $query->where('room_id', $roomId);
        } else {
            $query->whereIn('room_id', function ($q) {
                $q->select('id')->from('discuss_rooms')->where('type', 'public');
            });
        }

        if ($since) {
            $query->where('created_at', '>=', $since);
        }

        return max(0, (int) $query->sum('amount'));
    }

    private function since(string $period): ?Carbon
    {
        return match ($period) {
            'weekly' => now()->startOfWeek(),
            'monthly' => now()->startOfMonth(),
            default => null,
        };
    }
}
