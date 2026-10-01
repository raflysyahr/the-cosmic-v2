<?php

namespace App\Modules\Discuss\Services;

use App\Modules\Auth\Models\User;
use App\Modules\Discuss\Enums\CpSource;
use App\Modules\Discuss\Enums\MarkKind;
use App\Modules\Discuss\Enums\MessageType;
use App\Modules\Discuss\Events\MessageMarked;
use App\Modules\Discuss\Models\Member;
use App\Modules\Discuss\Models\Message;
use App\Modules\Discuss\Models\MessageMark;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

/**
 * Helpful & Best Answer. Semua aturan otorisasi ada di sini (AGENTS.md §4),
 * bukan di controller atau frontend.
 */
class MarkService
{
    public function __construct(
        private readonly ContributionService $contribution,
        private readonly CpSettingsService $settings,
    ) {}

    /**
     * Toggle Helpful. Siapa saja yang member room (bukan penulis pesan)
     * boleh menandai, dengan syarat anti-farming: email terverifikasi,
     * sudah member >= helpful_min_member_hours, dan batas harian.
     *
     * @return array{action: string, marks: array}
     */
    public function toggleHelpful(string $roomId, string $messageId, string $actorId): array
    {
        $cfg = $this->settings->all();
        $actor = $this->activeMember($roomId, $actorId);
        $message = $this->markableMessage($roomId, $messageId);

        if ((string) $message->user_id === (string) $actorId) {
            $this->fail('You cannot mark your own message as helpful.');
        }

        $existing = MessageMark::where('message_id', $message->id)
            ->where('marked_by', $actorId)
            ->where('kind', MarkKind::Helpful->value)
            ->first();

        if ($existing) {
            // Mencabut tanda TIDAK mencabut CP yang sudah diberikan (dedupe
            // per pesan mencegah mark/unmark berulang jadi farming); CP
            // hanya dicabut kalau pesannya dihapus.
            $existing->delete();

            return $this->finish($roomId, $message, MarkKind::Helpful, 'removed');
        }

        $user = User::select('id', 'email_verified_at')->where('id', $actorId)->first();
        if (! $user || $user->email_verified_at === null) {
            $this->fail('Verify your email before marking messages as helpful.');
        }

        $minHours = (int) $cfg['helpful_min_member_hours'];
        if ($minHours > 0 && $actor->joined_at && $actor->joined_at->gt(now()->subHours($minHours))) {
            $this->fail("You can mark messages as helpful after being a member for {$minHours} hours.");
        }

        $givenToday = MessageMark::where('marked_by', $actorId)
            ->where('kind', MarkKind::Helpful->value)
            ->where('created_at', '>=', now()->startOfDay())
            ->count();
        if ($givenToday >= (int) $cfg['helpful_daily_give_limit']) {
            $this->fail('You have reached today\'s limit for Helpful marks.');
        }

        try {
            MessageMark::create([
                'room_id' => $roomId,
                'message_id' => $message->id,
                'marked_by' => $actorId,
                'kind' => MarkKind::Helpful->value,
            ]);
        } catch (UniqueConstraintViolationException) {
            // Dua tap bersamaan — tanda sudah ada, anggap sukses.
            return $this->finish($roomId, $message, MarkKind::Helpful, 'added');
        }

        $this->contribution->awardForHelpful($message);

        return $this->finish($roomId, $message, MarkKind::Helpful, 'added');
    }

    /**
     * Toggle Best Answer pada sebuah reply. Hanya penulis pesan yang dibalas
     * (penanya) atau moderator/admin room. Maks satu per pertanyaan: untuk
     * mengganti, cabut dulu yang lama (mencabut juga menarik CP-nya).
     *
     * @return array{action: string, marks: array}
     */
    public function toggleBestAnswer(string $roomId, string $messageId, string $actorId): array
    {
        $actor = $this->activeMember($roomId, $actorId);
        $answer = $this->markableMessage($roomId, $messageId);

        if (! $answer->reply_to_id) {
            $this->fail('Only a reply can be marked as the best answer.');
        }

        $question = Message::where('id', $answer->reply_to_id)
            ->where('room_id', $roomId)
            ->first();
        if (! $question || $question->is_deleted) {
            $this->fail('The question for this answer no longer exists.');
        }

        $isAsker = (string) $question->user_id === (string) $actorId;
        $isModerator = in_array($actor->role->value, ['moderator', 'admin'], true);
        if (! $isAsker && ! $isModerator) {
            $this->fail('Only the person who asked, or a moderator, can choose the best answer.');
        }

        // Tidak ada CP dari interaksi dengan diri sendiri: penanda tidak
        // boleh memilih jawabannya sendiri, dan penanya tidak boleh
        // "menjawab" pertanyaannya sendiri.
        if ((string) $answer->user_id === (string) $actorId
            || (string) $answer->user_id === (string) $question->user_id) {
            $this->fail('You cannot mark your own answer as the best answer.');
        }

        $existing = MessageMark::where('message_id', $answer->id)
            ->where('kind', MarkKind::BestAnswer->value)
            ->first();

        if ($existing) {
            // Best Answer adalah STATUS: dicabut berarti CP-nya ditarik.
            $existing->delete();
            $this->contribution->revokeSourceForMessage($answer, CpSource::BestAnswer);

            return $this->finish($roomId, $answer, MarkKind::BestAnswer, 'removed');
        }

        $siblingIds = Message::where('room_id', $roomId)
            ->where('reply_to_id', $question->id)
            ->where('id', '!=', $answer->id)
            ->pluck('id');
        $alreadyChosen = $siblingIds->isNotEmpty()
            && MessageMark::whereIn('message_id', $siblingIds)
                ->where('kind', MarkKind::BestAnswer->value)
                ->exists();
        if ($alreadyChosen) {
            $this->fail('This question already has a best answer. Remove it first to choose another.');
        }

        try {
            MessageMark::create([
                'room_id' => $roomId,
                'message_id' => $answer->id,
                'marked_by' => $actorId,
                'kind' => MarkKind::BestAnswer->value,
            ]);
        } catch (UniqueConstraintViolationException) {
            return $this->finish($roomId, $answer, MarkKind::BestAnswer, 'added');
        }

        $this->contribution->awardForBestAnswer($answer);

        return $this->finish($roomId, $answer, MarkKind::BestAnswer, 'added');
    }

    /** State tanda sebuah pesan — dipakai response HTTP dan payload broadcast. */
    public function stateFor(string $messageId): array
    {
        $rows = MessageMark::where('message_id', $messageId)->select('kind', 'marked_by')->get();
        $helpful = $rows->filter(fn ($r) => $r->kind === MarkKind::Helpful)->pluck('marked_by')->values()->all();

        return [
            'helpful_count' => count($helpful),
            'helpful_user_ids' => $helpful,
            'is_best_answer' => $rows->contains(fn ($r) => $r->kind === MarkKind::BestAnswer),
        ];
    }

    private function finish(string $roomId, Message $message, MarkKind $kind, string $action): array
    {
        $marks = $this->stateFor($message->id);

        event(new MessageMarked(
            roomId: $roomId,
            messageId: (string) $message->id,
            kind: $kind->value,
            action: $action,
            helpfulUserIds: $marks['helpful_user_ids'],
            isBestAnswer: $marks['is_best_answer'],
        ));

        return ['action' => $action, 'marks' => $marks];
    }

    private function activeMember(string $roomId, string $userId): Member
    {
        $member = Member::where('room_id', $roomId)->where('user_id', $userId)->first();

        if (! $member || $member->is_banned) {
            $this->fail('You must be a member of this room.');
        }

        return $member;
    }

    private function markableMessage(string $roomId, string $messageId): Message
    {
        $message = Message::where('id', $messageId)->where('room_id', $roomId)->first();

        if (! $message || $message->is_deleted) {
            $this->fail('Message not found.');
        }

        $type = $message->type instanceof MessageType ? $message->type : MessageType::tryFrom((string) $message->type);
        if ($type === MessageType::System) {
            $this->fail('System messages cannot be marked.');
        }

        return $message;
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['mark' => [$message]]);
    }
}
