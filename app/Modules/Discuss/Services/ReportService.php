<?php

namespace App\Modules\Discuss\Services;

use App\Modules\Auth\Models\User;
use App\Modules\Discuss\Enums\MessageType;
use App\Modules\Discuss\Enums\PenaltyType;
use App\Modules\Discuss\Enums\ReportStatus;
use App\Modules\Discuss\Models\Member;
use App\Modules\Discuss\Models\Message;
use App\Modules\Discuss\Models\Report;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Laporan pesan + peninjauan moderator. Semua aturan otorisasi ada di sini
 * (AGENTS.md §4). Key error validasi: "report".
 */
class ReportService
{
    public function __construct(
        private readonly ContributionService $contribution,
        private readonly MessageService $messageService,
        private readonly CpSettingsService $settings,
    ) {}

    /** @return array<string, mixed> */
    public function submit(string $roomId, string $messageId, string $reporterId, string $reason, ?string $note): array
    {
        $cfg = $this->settings->all();
        $this->activeMember($roomId, $reporterId);

        $message = Message::where('id', $messageId)->where('room_id', $roomId)->first();
        if (! $message || $message->is_deleted) {
            $this->fail('Message not found.');
        }

        $type = $message->type instanceof MessageType ? $message->type : MessageType::tryFrom((string) $message->type);
        if ($type === MessageType::System) {
            $this->fail('System messages cannot be reported.');
        }

        if ((string) $message->user_id === (string) $reporterId) {
            $this->fail('You cannot report your own message.');
        }

        if (Report::where('message_id', $message->id)->where('reporter_id', $reporterId)->exists()) {
            $this->fail('You already reported this message.');
        }

        $today = Report::where('reporter_id', $reporterId)
            ->where('created_at', '>=', now()->startOfDay())
            ->count();
        if ($today >= (int) $cfg['report_daily_limit']) {
            $this->fail('You have reached today\'s limit for reports.');
        }

        try {
            $report = Report::create([
                'room_id' => $roomId,
                'message_id' => $message->id,
                'message_author_id' => $message->user_id,
                'reporter_id' => $reporterId,
                'reason' => $reason,
                'note' => $note !== null && trim($note) !== '' ? trim($note) : null,
                'status' => ReportStatus::Pending->value,
            ]);
        } catch (UniqueConstraintViolationException) {
            $this->fail('You already reported this message.');
        }

        return $this->present($report, $message, null, null);
    }

    /**
     * Antrian laporan untuk moderator/admin room.
     *
     * @return list<array<string, mixed>>
     */
    public function listForRoom(string $roomId, string $actorId, string $status = 'pending', int $limit = 50): array
    {
        $this->assertModerator($roomId, $actorId);

        $statusEnum = ReportStatus::tryFrom($status) ?? ReportStatus::Pending;

        $reports = Report::where('room_id', $roomId)
            ->where('status', $statusEnum->value)
            ->orderBy('created_at', $statusEnum === ReportStatus::Pending ? 'asc' : 'desc')
            ->limit($limit)
            ->get();

        if ($reports->isEmpty()) {
            return [];
        }

        $messages = Message::whereIn('id', $reports->pluck('message_id'))->get()->keyBy('id');
        $users = User::select('id', 'username', 'display_name', 'avatar_url')
            ->whereIn('id', $reports->pluck('reporter_id')->merge($reports->pluck('message_author_id'))->unique())
            ->get()
            ->keyBy('id');

        return $reports->map(fn (Report $report) => $this->present(
            $report,
            $messages->get($report->message_id),
            $users->get($report->message_author_id),
            $users->get($report->reporter_id),
        ))->values()->all();
    }

    /**
     * Tutup laporan. `valid` memberi CP ke pelapor, dan (opsional) penalti
     * ke penulis pesan serta penghapusan pesan; `dismissed` tidak
     * menyentuh siapa pun.
     *
     * @return array<string, mixed>
     */
    public function resolve(string $roomId, string $reportId, string $actorId, string $outcome, ?string $penalty, bool $deleteMessage): array
    {
        $this->assertModerator($roomId, $actorId);

        $report = Report::where('id', $reportId)->where('room_id', $roomId)->first();
        if (! $report) {
            $this->fail('Report not found.');
        }

        $status = ReportStatus::tryFrom($outcome);
        if (! in_array($status, [ReportStatus::Valid, ReportStatus::Dismissed], true)) {
            $this->fail('Outcome must be valid or dismissed.');
        }

        $penaltyType = PenaltyType::tryFrom($penalty ?? 'none') ?? PenaltyType::None;
        if ($status === ReportStatus::Dismissed) {
            $penaltyType = PenaltyType::None;
            $deleteMessage = false;
        }

        // Update bersyarat: dua moderator yang menutup laporan yang sama
        // bersamaan — hanya satu yang menang, sehingga poin/penalti tidak
        // dijatuhkan dua kali.
        $won = Report::where('id', $report->id)
            ->where('status', ReportStatus::Pending->value)
            ->update([
                'status' => $status->value,
                'penalty' => $penaltyType->value,
                'resolved_by' => $actorId,
                'resolved_at' => now(),
            ]);
        if ($won === 0) {
            $this->fail('This report has already been resolved.');
        }

        $report->refresh();
        $message = Message::find($report->message_id);

        if ($status === ReportStatus::Valid) {
            $this->contribution->awardForValidReport($report);

            $settingKey = $penaltyType->settingKey();
            if ($settingKey !== null) {
                $this->contribution->applyPenalty(
                    $roomId,
                    (string) $report->message_author_id,
                    (int) $this->settings->all()[$settingKey],
                    'report:' . $report->id,
                );
            }

            if ($deleteMessage && $message && ! $message->is_deleted) {
                $this->messageService->delete($message, $actorId);
            }
        }

        return $this->present($report, $message?->fresh(), null, null);
    }

    /** @return array<string, mixed> */
    private function present(Report $report, ?Message $message, ?User $author, ?User $reporter): array
    {
        return [
            'id' => $report->id,
            'status' => $report->status->value,
            'reason' => $report->reason->value,
            'note' => $report->note,
            'penalty' => $report->penalty?->value,
            'created_at' => $report->created_at?->toIso8601String(),
            'resolved_at' => $report->resolved_at?->toIso8601String(),
            'message' => [
                'id' => $report->message_id,
                'body' => $message && ! $message->is_deleted ? Str::limit((string) $message->body, 240) : null,
                'is_deleted' => $message ? (bool) $message->is_deleted : true,
            ],
            'author' => $author ? [
                'id' => $author->id,
                'username' => $author->username,
                'display_name' => $author->display_name,
            ] : ['id' => $report->message_author_id],
            'reporter' => $reporter ? [
                'id' => $reporter->id,
                'username' => $reporter->username,
                'display_name' => $reporter->display_name,
            ] : ['id' => $report->reporter_id],
        ];
    }

    private function activeMember(string $roomId, string $userId): Member
    {
        $member = Member::where('room_id', $roomId)->where('user_id', $userId)->first();

        if (! $member || $member->is_banned) {
            $this->fail('You must be a member of this room.');
        }

        return $member;
    }

    private function assertModerator(string $roomId, string $userId): void
    {
        $member = $this->activeMember($roomId, $userId);

        if (! in_array($member->role->value, ['moderator', 'admin'], true)) {
            $this->fail('Only moderators can review reports.');
        }
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['report' => [$message]]);
    }
}
