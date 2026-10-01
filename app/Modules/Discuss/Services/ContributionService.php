<?php

namespace App\Modules\Discuss\Services;

use App\Modules\Auth\Models\User;
use App\Modules\Discuss\Enums\CpSource;
use App\Modules\Discuss\Enums\MessageType;
use App\Modules\Discuss\Events\ContributionAwarded;
use App\Modules\Discuss\Models\CpLog;
use App\Modules\Discuss\Models\Member;
use App\Modules\Discuss\Models\Message;
use App\Modules\Discuss\Models\Report;
use App\Modules\Discuss\Models\Room;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Sistem Contribution Points (CP) Discuss. Saldo tetap disimpan di
 * discuss_members.xp_points lewat RankService::awardXp() (supaya rank,
 * profil & UI lama tetap jalan); service ini menambah lapisan aturan di
 * atasnya: kualitas, batas harian, dedupe, multiplier event, dan ledger
 * discuss_cp_logs.
 */
class ContributionService
{
    public function __construct(
        private readonly RankService $rankService,
        private readonly CpSettingsService $settings,
        private readonly CpEventService $events,
        private readonly AchievementService $achievements,
    ) {}

    /** Poin untuk penulis pesan (source message / reply), dan reply_received untuk penulis pesan induk. */
    public function awardForMessage(Message $message): ?CpLog
    {
        $cfg = $this->settings->all();
        if (! $cfg['enabled']) {
            return null;
        }

        $type = $message->type instanceof MessageType ? $message->type : MessageType::tryFrom((string) $message->type);
        if ($type === MessageType::System) {
            return null;
        }

        $room = Room::find($message->room_id);
        if (! $this->roomAllowsCp($room)) {
            return null;
        }

        $member = Member::where('room_id', $message->room_id)
            ->where('user_id', $message->user_id)
            ->first();
        if (! $member || $member->is_banned) {
            return null;
        }

        if (! $this->passesQualityFilter($message, $cfg)
            || $this->isBurst($message, $cfg)
            || $this->isDuplicate($message, $cfg)) {
            return null;
        }

        // Reply ke pesan orang lain = source reply. Reply ke pesan sendiri
        // (atau pesan induk yang tidak ditemukan) dihitung pesan biasa —
        // tidak ada CP dari interaksi dengan diri sendiri.
        $source = CpSource::Message;
        $parent = null;
        if ($message->reply_to_id) {
            $parent = Message::select('id', 'user_id', 'room_id', 'is_deleted')
                ->where('id', $message->reply_to_id)
                ->first();
            if ($parent
                && (string) $parent->room_id === (string) $message->room_id
                && (string) $parent->user_id !== (string) $message->user_id) {
                $source = CpSource::Reply;
            }
        }

        $dayStart = now()->startOfDay();

        if ($source === CpSource::Message) {
            $used = (int) CpLog::where('user_id', $message->user_id)
                ->where('source', CpSource::Message->value)
                ->where('created_at', '>=', $dayStart)
                ->sum('base_amount');
            $base = min($cfg['message_points'], max(0, $cfg['message_daily_cap'] - $used));
        } else {
            $index = CpLog::where('user_id', $message->user_id)
                ->where('source', CpSource::Reply->value)
                ->where('created_at', '>=', $dayStart)
                ->count() + 1;

            $base = match (true) {
                $index <= $cfg['reply_tier1_limit'] => $cfg['reply_tier1_points'],
                $index <= $cfg['reply_tier2_limit'] => $cfg['reply_tier2_points'],
                default => 0,
            };
        }

        if ($base <= 0) {
            return null;
        }

        $log = $this->grant($member, $source, (string) $message->id, (string) $message->id, $base);

        if ($log && $source === CpSource::Reply && $parent && ! $parent->is_deleted) {
            $this->awardReplyReceived($message, $parent, $cfg);
        }

        if ($log) {
            $this->maybeAwardDailyBonus($member, $cfg);
        }

        return $log;
    }

    /** Helpful: poin untuk penulis pesan, sekali per pesan (dedupe lewat referensi = id pesan). */
    public function awardForHelpful(Message $message): ?CpLog
    {
        return $this->awardStatus($message, CpSource::Helpful, 'helpful_points');
    }

    /** Best Answer: poin untuk penulis jawaban, sekali per jawaban. */
    public function awardForBestAnswer(Message $message): ?CpLog
    {
        return $this->awardStatus($message, CpSource::BestAnswer, 'best_answer_points');
    }

    private function awardStatus(Message $message, CpSource $source, string $pointsKey): ?CpLog
    {
        $cfg = $this->settings->all();
        if (! $cfg['enabled'] || $cfg[$pointsKey] <= 0) {
            return null;
        }

        if (! $this->roomAllowsCp(Room::find($message->room_id))) {
            return null;
        }

        $author = Member::where('room_id', $message->room_id)
            ->where('user_id', $message->user_id)
            ->first();
        if (! $author || $author->is_banned) {
            return null;
        }

        return $this->grant($author, $source, (string) $message->id, (string) $message->id, (int) $cfg[$pointsKey]);
    }

    /**
     * Bonus harian (sekali per hari untuk semua room) lalu cek milestone
     * streak. Dipanggil hanya setelah sebuah pesan/reply BENAR-BENAR
     * memberi CP, jadi pesan spam/duplikat/emoji tidak ikut memenuhi syarat.
     */
    private function maybeAwardDailyBonus(Member $member, array $cfg): void
    {
        $base = (int) $cfg['daily_bonus_points'];
        if ($base <= 0) {
            return;
        }

        $today = now()->toDateString();

        $already = CpLog::where('user_id', $member->user_id)
            ->where('source', CpSource::DailyBonus->value)
            ->where('reference', $today)
            ->exists();
        if ($already) {
            return;
        }

        $dayStart = now()->startOfDay();
        $countToday = fn (CpSource $source) => CpLog::where('user_id', $member->user_id)
            ->where('source', $source->value)
            ->where('created_at', '>=', $dayStart)
            ->count();

        $minReplies = (int) $cfg['daily_bonus_min_replies'];
        $minMessages = (int) $cfg['daily_bonus_min_messages'];

        $qualifies = ($minReplies > 0 && $countToday(CpSource::Reply) >= $minReplies)
            || ($minMessages > 0 && $countToday(CpSource::Message) >= $minMessages);
        if (! $qualifies) {
            return;
        }

        if ($this->grant($member, CpSource::DailyBonus, $today, null, $base)) {
            $this->maybeAwardStreak($member, $cfg);
        }
    }

    /**
     * Streak = hari berturut-turut (sampai hari ini) yang punya log bonus
     * harian. Milestone dibayar sekali per rangkaian streak: referensi
     * memuat hari pertama rangkaian, jadi streak baru setelah putus bisa
     * mendapat milestone lagi, tapi streak yang sama tidak.
     */
    private function maybeAwardStreak(Member $member, array $cfg): void
    {
        $days = CpLog::where('user_id', $member->user_id)
            ->where('source', CpSource::DailyBonus->value)
            ->where('reference', '>=', now()->subDays(35)->toDateString())
            ->pluck('reference')
            ->flip();

        $length = 0;
        $cursor = now()->startOfDay();
        while ($days->has($cursor->toDateString())) {
            $length++;
            $cursor = $cursor->subDay();
        }

        $rewards = [
            3 => (int) $cfg['streak_3_points'],
            7 => (int) $cfg['streak_7_points'],
            14 => (int) $cfg['streak_14_points'],
            30 => (int) $cfg['streak_30_points'],
        ];

        $points = $rewards[$length] ?? 0;
        if ($points <= 0) {
            return;
        }

        $streakStart = now()->startOfDay()->subDays($length - 1)->toDateString();

        $this->grant($member, CpSource::Streak, $length . ':' . $streakStart, null, $points);
    }

    /** Poin untuk penulis pesan saat orang lain memberi reaksi. */
    public function awardForReaction(string $roomId, string $messageId, string $reactorId): ?CpLog
    {
        $cfg = $this->settings->all();
        if (! $cfg['enabled']) {
            return null;
        }

        $message = Message::where('id', $messageId)->where('room_id', $roomId)->first();
        if (! $message || $message->is_deleted) {
            return null;
        }

        // Tidak ada CP dari reaksi ke konten sendiri.
        if ((string) $message->user_id === (string) $reactorId) {
            return null;
        }

        if ($cfg['require_verified_reactor']) {
            $reactor = User::select('id', 'email_verified_at')->where('id', $reactorId)->first();
            if (! $reactor || $reactor->email_verified_at === null) {
                return null;
            }
        }

        if (! $this->roomAllowsCp(Room::find($roomId))) {
            return null;
        }

        $author = Member::where('room_id', $roomId)->where('user_id', $message->user_id)->first();
        if (! $author || $author->is_banned) {
            return null;
        }

        $used = (int) CpLog::where('user_id', $message->user_id)
            ->where('source', CpSource::ReactionReceived->value)
            ->where('subject_id', $message->id)
            ->sum('base_amount');
        $base = min($cfg['reaction_points'], max(0, $cfg['reaction_cap_per_message'] - $used));
        if ($base <= 0) {
            return null;
        }

        // Kunci dedupe per (pesan, reaktor): ganti emote / toggle berulang
        // tidak bisa menambah CP lagi, dan un-react tidak mencabutnya.
        return $this->grant($author, CpSource::ReactionReceived, $messageId . ':' . $reactorId, (string) $message->id, $base);
    }

    /**
     * Cabut semua CP yang bersumber dari pesan ini (poin penulisnya,
     * reaksi, reply, Helpful & Best Answer yang diterimanya). Dicatat
     * sebagai entri negatif di ledger — entri asli tidak diubah, dan batas
     * harian tetap menghitung entri aslinya, jadi hapus-lalu-kirim-ulang
     * tidak bisa dipakai farming.
     *
     * @return int jumlah entri yang dicabut
     */
    public function revokeForMessage(Message $message): int
    {
        $id = (string) $message->id;

        $logs = CpLog::where('amount', '>', 0)
            ->where(function ($q) use ($id) {
                $q->where('reference', $id)
                    ->orWhere('subject_id', $id)
                    ->orWhere('reference', 'like', $id . ':%');
            })
            ->get();

        return $this->revokeLogs($logs);
    }

    /** Cabut hanya CP satu source untuk pesan ini (mis. Best Answer dilepas). */
    public function revokeSourceForMessage(Message $message, CpSource $source): int
    {
        $logs = CpLog::where('amount', '>', 0)
            ->where('source', $source->value)
            ->where('reference', (string) $message->id)
            ->get();

        return $this->revokeLogs($logs);
    }

    /** @param \Illuminate\Support\Collection<int, CpLog> $logs */
    private function revokeLogs($logs): int
    {
        $revoked = 0;

        foreach ($logs as $log) {
            $revokeRef = 'revoke:' . $log->id;

            $already = CpLog::where('user_id', $log->user_id)
                ->where('source', CpSource::Revoke->value)
                ->where('reference', $revokeRef)
                ->exists();
            if ($already) {
                continue;
            }

            DB::transaction(function () use ($log, $revokeRef) {
                CpLog::create([
                    'user_id' => $log->user_id,
                    'room_id' => $log->room_id,
                    'source' => CpSource::Revoke->value,
                    'reference' => $revokeRef,
                    'subject_id' => $log->subject_id,
                    'base_amount' => 0,
                    'multiplier_pct' => 100,
                    'amount' => -$log->amount,
                ]);

                $member = Member::where('room_id', $log->room_id)
                    ->where('user_id', $log->user_id)
                    ->first();
                if ($member) {
                    $member->update(['xp_points' => max(0, $member->xp_points - $log->amount)]);
                }
            });

            $revoked++;
        }

        return $revoked;
    }

    private function awardReplyReceived(Message $reply, Message $parent, array $cfg): void
    {
        $author = Member::where('room_id', $parent->room_id)->where('user_id', $parent->user_id)->first();
        if (! $author || $author->is_banned) {
            return;
        }

        $used = (int) CpLog::where('user_id', $parent->user_id)
            ->where('source', CpSource::ReplyReceived->value)
            ->where('subject_id', $parent->id)
            ->sum('base_amount');
        $base = min($cfg['reply_received_points'], max(0, $cfg['reply_received_cap_per_message'] - $used));
        if ($base <= 0) {
            return;
        }

        $this->grant($author, CpSource::ReplyReceived, (string) $reply->id, (string) $parent->id, $base);
    }

    /**
     * Satu-satunya tempat CP masuk: cek dedupe, terapkan multiplier event,
     * tulis ledger + saldo dalam satu transaksi, cek promosi rank, lalu
     * kirim notifikasi "+N CP".
     */
    private function grant(Member $member, CpSource $source, string $reference, ?string $subjectId, int $base, ?string $detail = null): ?CpLog
    {
        $exists = CpLog::where('user_id', $member->user_id)
            ->where('source', $source->value)
            ->where('reference', $reference)
            ->exists();
        if ($exists) {
            return null;
        }

        // Achievement adalah milestone sekali seumur hidup — tidak ikut
        // digandakan event multiplier (walau event menarget semua source).
        $event = $source === CpSource::Achievement
            ? null
            : $this->events->resolveFor($source, (string) $member->room_id);
        $pct = $event?->multiplier_pct ?? 100;
        $amount = (int) round($base * $pct / 100);

        try {
            $log = DB::transaction(function () use ($member, $source, $reference, $subjectId, $base, $pct, $amount, $event) {
                $log = CpLog::create([
                    'user_id' => $member->user_id,
                    'room_id' => $member->room_id,
                    'source' => $source->value,
                    'reference' => $reference,
                    'subject_id' => $subjectId,
                    'base_amount' => $base,
                    'multiplier_pct' => $pct,
                    'amount' => $amount,
                    'event_id' => $event?->id,
                ]);

                $this->rankService->awardXp($member, $amount);

                return $log;
            });
        } catch (UniqueConstraintViolationException) {
            // Dua request bersamaan untuk sumber yang sama — yang kedua kalah.
            return null;
        }

        // CheckRankPromotion hanya jalan untuk PENGIRIM pesan; penerima
        // reaksi/reply perlu dicek di sini supaya rank-nya ikut naik.
        $this->rankService->checkPromotion($member->refresh());

        event(new ContributionAwarded(
            userId: (string) $member->user_id,
            roomId: (string) $member->room_id,
            source: $source->value,
            amount: $amount,
            baseAmount: $base,
            multiplierPct: $pct,
            eventName: $event?->name,
            detail: $detail,
        ));

        $this->grantAchievements($member, $source);

        return $log;
    }

    /** Bayar achievement yang baru terpenuhi oleh CP `$trigger` ini (tiap achievement hanya sekali). */
    private function grantAchievements(Member $member, CpSource $trigger): void
    {
        foreach ($this->achievements->pending((string) $member->user_id, $trigger) as $key => $achievement) {
            $this->grant($member, CpSource::Achievement, $key, null, $achievement['points'], $achievement['name']);
        }
    }

    /**
     * Poin untuk pelapor setelah moderator menilai laporannya valid.
     * subject_id sengaja null: poin pelapor tidak ikut tercabut saat pesan
     * yang dilaporkan dihapus sebagai tindak lanjut laporan itu.
     */
    public function awardForValidReport(Report $report): ?CpLog
    {
        $cfg = $this->settings->all();
        $points = (int) $cfg['report_valid_points'];
        if (! $cfg['enabled'] || $points <= 0) {
            return null;
        }

        if (! $this->roomAllowsCp(Room::find($report->room_id))) {
            return null;
        }

        $reporter = Member::where('room_id', $report->room_id)
            ->where('user_id', $report->reporter_id)
            ->first();
        if (! $reporter || $reporter->is_banned) {
            return null;
        }

        return $this->grant($reporter, CpSource::ReportValid, (string) $report->id, null, $points);
    }

    /**
     * Penalti oleh moderator. Dicatat sebagai entri negatif di ledger;
     * saldo tidak turun di bawah 0 dan rank tidak diturunkan. Dedupe per
     * `$reference` ("report:{id}"), jadi laporan yang sama tidak bisa
     * menjatuhkan penalti dua kali. Tidak ada toast ke penerima.
     */
    public function applyPenalty(string $roomId, string $userId, int $points, string $reference): ?CpLog
    {
        if ($points <= 0) {
            return null;
        }

        $member = Member::where('room_id', $roomId)->where('user_id', $userId)->first();
        if (! $member) {
            return null;
        }

        $exists = CpLog::where('user_id', $userId)
            ->where('source', CpSource::Penalty->value)
            ->where('reference', $reference)
            ->exists();
        if ($exists) {
            return null;
        }

        try {
            return DB::transaction(function () use ($member, $roomId, $userId, $points, $reference) {
                $log = CpLog::create([
                    'user_id' => $userId,
                    'room_id' => $roomId,
                    'source' => CpSource::Penalty->value,
                    'reference' => $reference,
                    'subject_id' => null,
                    'base_amount' => 0,
                    'multiplier_pct' => 100,
                    'amount' => -$points,
                ]);

                $member->refresh();
                $member->update(['xp_points' => max(0, $member->xp_points - $points)]);

                return $log;
            });
        } catch (UniqueConstraintViolationException) {
            return null;
        }
    }

    /** DM tidak memberi CP (mudah di-farm dengan akun kedua); room bisa dimatikan lewat xp_per_message = 0. */
    private function roomAllowsCp(?Room $room): bool
    {
        if (! $room) {
            return false;
        }

        $settings = $room->settings ?? [];

        return ! ($settings['is_direct'] ?? false)
            && ($settings['xp_per_message'] ?? null) !== 0;
    }

    private function passesQualityFilter(Message $message, array $cfg): bool
    {
        if (! empty($message->attachments)) {
            return true;
        }

        $body = trim((string) $message->body);
        $alnum = preg_match_all('/[\p{L}\p{N}]/u', $body);

        return $alnum !== false && $alnum >= $cfg['min_alnum_chars'];
    }

    /** Terlalu banyak pesan dalam waktu sangat singkat (pesan saat ini ikut dihitung). */
    private function isBurst(Message $message, array $cfg): bool
    {
        $recent = Message::where('user_id', $message->user_id)
            ->where('created_at', '>=', now()->subSeconds($cfg['burst_window_seconds']))
            ->count();

        return $recent > $cfg['burst_count'];
    }

    /** Isi sama persis dengan pesan lain dari user yang sama dalam jendela waktu. */
    private function isDuplicate(Message $message, array $cfg): bool
    {
        $body = trim((string) $message->body);
        if ($body === '') {
            return false;
        }

        return Message::where('user_id', $message->user_id)
            ->where('id', '!=', $message->id)
            ->where('body', $message->body)
            ->where('created_at', '>=', now()->subHours($cfg['duplicate_window_hours']))
            ->exists();
    }
}
