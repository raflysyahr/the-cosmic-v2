<?php

namespace App\Modules\Discuss\Services;

use App\Modules\Discuss\Enums\CpSource;
use App\Modules\Discuss\Models\CpLog;

/**
 * Achievement CP: milestone sekali seumur hidup per user (lintas room).
 * Tidak punya tabel sendiri — "sudah didapat" = ada baris ledger
 * source=achievement dengan reference=<key>, dan progres dihitung dari
 * ledger yang sama. Service ini murni membaca; yang menulis poin tetap
 * ContributionService::grant() (satu pintu masuk CP).
 */
class AchievementService
{
    /**
     * `source` = jenis log yang dihitung sebagai progres sekaligus pemicu
     * pengecekan. Thread/"First Thread" dari proposal tidak ada di model
     * chat, jadi padanannya "First Reply".
     */
    public const DEFINITIONS = [
        'first_reply' => [
            'name' => 'First Reply',
            'description' => 'Write your first valid reply.',
            'source' => 'reply',
            'threshold' => 1,
            'setting' => 'achievement_first_reply_points',
        ],
        'replies_100' => [
            'name' => '100 Replies',
            'description' => 'Write 100 valid replies.',
            'source' => 'reply',
            'threshold' => 100,
            'setting' => 'achievement_replies_100_points',
        ],
        'likes_100' => [
            'name' => '100 Reactions',
            'description' => 'Receive 100 reactions on your messages.',
            'source' => 'reaction_received',
            'threshold' => 100,
            'setting' => 'achievement_likes_100_points',
        ],
        'helpful_10' => [
            'name' => '10 Helpful',
            'description' => 'Have 10 of your messages marked helpful.',
            'source' => 'helpful',
            'threshold' => 10,
            'setting' => 'achievement_helpful_10_points',
        ],
        'best_answer_10' => [
            'name' => '10 Best Answers',
            'description' => 'Have 10 of your answers chosen as the best answer.',
            'source' => 'best_answer',
            'threshold' => 10,
            'setting' => 'achievement_best_answer_10_points',
        ],
        'active_30' => [
            'name' => 'Active 30 Days',
            'description' => 'Be active on 30 different days.',
            'source' => 'daily_bonus',
            'threshold' => 30,
            'setting' => 'achievement_active_30_points',
        ],
    ];

    public function __construct(
        private readonly CpSettingsService $settings,
    ) {}

    /**
     * Achievement yang baru terpenuhi oleh `$trigger` dan belum pernah
     * dibayar. @return array<string, array{name: string, points: int}>
     */
    public function pending(string $userId, CpSource $trigger): array
    {
        $cfg = $this->settings->all();
        $revoked = $this->revokedLogIds($userId);
        $unlocked = $this->unlockedAt($userId);
        $pending = [];

        foreach (self::DEFINITIONS as $key => $def) {
            if ($def['source'] !== $trigger->value || isset($unlocked[$key])) {
                continue;
            }

            $points = (int) ($cfg[$def['setting']] ?? 0);
            if ($points <= 0) {
                continue;
            }

            if ($this->count($userId, $def['source'], $revoked) >= $def['threshold']) {
                $pending[$key] = ['name' => $def['name'], 'points' => $points];
            }
        }

        return $pending;
    }

    /** Daftar lengkap untuk halaman UI: progres, poin, dan kapan didapat. */
    public function progress(string $userId): array
    {
        $cfg = $this->settings->all();
        $revoked = $this->revokedLogIds($userId);
        $unlocked = $this->unlockedAt($userId);
        $counts = [];
        $rows = [];

        foreach (self::DEFINITIONS as $key => $def) {
            $counts[$def['source']] ??= $this->count($userId, $def['source'], $revoked);

            $rows[] = [
                'key' => $key,
                'name' => $def['name'],
                'description' => $def['description'],
                'points' => (int) ($cfg[$def['setting']] ?? 0),
                'threshold' => $def['threshold'],
                'progress' => min($counts[$def['source']], $def['threshold']),
                'unlocked' => isset($unlocked[$key]),
                'unlocked_at' => $unlocked[$key] ?? null,
            ];
        }

        return $rows;
    }

    /**
     * Jumlah log positif satu source, dikurangi yang sudah dicabut — supaya
     * konten yang dihapus tidak ikut menghitung progres.
     *
     * @param list<string> $revokedLogIds
     */
    private function count(string $userId, string $source, array $revokedLogIds): int
    {
        return CpLog::where('user_id', $userId)
            ->where('source', $source)
            ->where('amount', '>', 0)
            ->when($revokedLogIds !== [], fn ($q) => $q->whereNotIn('id', $revokedLogIds))
            ->count();
    }

    /** Entri pencabutan menyimpan id log asal di reference ("revoke:{id}"). */
    private function revokedLogIds(string $userId): array
    {
        return CpLog::where('user_id', $userId)
            ->where('source', CpSource::Revoke->value)
            ->pluck('reference')
            ->map(fn ($reference) => substr((string) $reference, strlen('revoke:')))
            ->all();
    }

    /** @return array<string, string> key achievement => waktu didapat (ISO 8601) */
    private function unlockedAt(string $userId): array
    {
        return CpLog::where('user_id', $userId)
            ->where('source', CpSource::Achievement->value)
            ->get(['reference', 'created_at'])
            ->mapWithKeys(fn ($log) => [(string) $log->reference => $log->created_at?->toIso8601String()])
            ->all();
    }
}
