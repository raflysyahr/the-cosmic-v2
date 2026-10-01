<?php

namespace App\Modules\Discuss\tests\Unit;

use App\Modules\Auth\Models\User;
use App\Modules\Discuss\Data\SendMessageData;
use App\Modules\Discuss\Events\ContributionAwarded;
use App\Modules\Discuss\Models\CpEvent;
use App\Modules\Discuss\Models\CpLog;
use App\Modules\Discuss\Models\CpSetting;
use App\Modules\Discuss\Models\Member;
use App\Modules\Discuss\Models\Message;
use App\Modules\Discuss\Models\Room;
use App\Modules\Discuss\Services\AchievementService;
use App\Modules\Discuss\Services\ContributionService;
use App\Modules\Discuss\Services\MessageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/** Fase 3: achievement dan penalti (report dites di ReportControllerTest). */
class ContributionPhase3Test extends TestCase
{
    use RefreshDatabase;

    private ContributionService $service;
    private MessageService $messages;
    private Room $room;
    private User $alice;
    private User $bob;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake([ContributionAwarded::class]);
        // Bonus harian dimatikan supaya test achievement tidak tercampur;
        // test active_30 menyalakannya lagi.
        config(['discuss_cp.daily_bonus_points' => 0]);

        $this->service = app(ContributionService::class);
        $this->messages = app(MessageService::class);
        $this->room = Room::factory()->create();
        $this->alice = User::factory()->create();
        $this->bob = User::factory()->create();

        foreach ([$this->alice, $this->bob] as $user) {
            Member::create([
                'room_id' => $this->room->id, 'user_id' => $user->id, 'role' => 'member', 'xp_points' => 0,
                'is_banned' => false, 'joined_at' => now()->subDays(2), 'last_read_at' => now(),
            ]);
        }
    }

    private function send(User $user, string $body, ?string $replyToId = null): Message
    {
        return $this->messages->send(new SendMessageData(
            roomId: $this->room->id, userId: $user->id, replyToId: $replyToId,
            type: 'text', body: $body, attachments: [], metadata: [],
        ));
    }

    /** Baris ledger di masa lalu (di luar batas harian hari ini). */
    private function seed(User $user, string $source, int $count, int $amount = 1): array
    {
        $ids = [];
        for ($i = 0; $i < $count; $i++) {
            $log = CpLog::unguarded(fn () => CpLog::create([
                'user_id' => $user->id,
                'room_id' => $this->room->id,
                'source' => $source,
                'reference' => "seed-{$source}-" . uniqid('', true),
                'base_amount' => $amount,
                'multiplier_pct' => 100,
                'amount' => $amount,
                'created_at' => now()->subDays(3),
            ]));
            $ids[] = $log->id;
        }

        return $ids;
    }

    private function achievementLogs(User $user, ?string $key = null)
    {
        return CpLog::where('user_id', $user->id)
            ->where('source', 'achievement')
            ->when($key, fn ($q) => $q->where('reference', $key));
    }

    public function test_first_reply_unlocks_once_and_is_announced_with_its_name(): void
    {
        $question = $this->send($this->bob, 'Does anyone know the answer here?');

        $this->send($this->alice, 'Yes, it is the cache layer', $question->id);
        $this->send($this->alice, 'And also the session driver', $question->id);

        $logs = $this->achievementLogs($this->alice, 'first_reply')->get();
        $this->assertCount(1, $logs);
        $this->assertSame(20, $logs->first()->amount);

        Event::assertDispatched(
            ContributionAwarded::class,
            fn ($e) => $e->source === 'achievement' && $e->amount === 20 && $e->detail === 'First Reply',
        );
    }

    public function test_hundredth_reply_unlocks_its_milestone(): void
    {
        $this->seed($this->alice, 'reply', 99, 4);
        $question = $this->send($this->bob, 'Does anyone know the answer here?');

        $this->send($this->alice, 'Reply number one hundred today', $question->id);

        $this->assertSame(1, $this->achievementLogs($this->alice, 'replies_100')->count());
        $this->assertSame(50, $this->achievementLogs($this->alice, 'replies_100')->value('amount'));
    }

    public function test_hundredth_reaction_unlocks_for_the_author(): void
    {
        $this->seed($this->bob, 'reaction_received', 99, 2);
        $message = $this->send($this->bob, 'A post that everyone likes');

        $this->service->awardForReaction($this->room->id, $message->id, $this->alice->id);

        $this->assertSame(1, $this->achievementLogs($this->bob, 'likes_100')->count());
    }

    public function test_tenth_helpful_and_tenth_best_answer_unlock_from_their_own_triggers(): void
    {
        $this->seed($this->alice, 'helpful', 9, 15);
        $this->seed($this->alice, 'best_answer', 9, 25);

        $helpfulMessage = $this->send($this->alice, 'A very helpful explanation');
        $this->service->awardForHelpful($helpfulMessage);
        $answerMessage = $this->send($this->alice, 'The accepted solution to it');
        $this->service->awardForBestAnswer($answerMessage);

        $this->assertSame(1, $this->achievementLogs($this->alice, 'helpful_10')->count());
        $this->assertSame(100, $this->achievementLogs($this->alice, 'helpful_10')->value('amount'));
        $this->assertSame(1, $this->achievementLogs($this->alice, 'best_answer_10')->count());
    }

    public function test_active_thirty_days_unlocks_from_the_daily_bonus(): void
    {
        config(['discuss_cp.daily_bonus_points' => 10]);
        $this->seed($this->alice, 'daily_bonus', 29, 10);
        $question = $this->send($this->bob, 'Does anyone know the answer here?');

        $this->send($this->alice, 'Reply that triggers the bonus', $question->id);

        $this->assertSame(1, $this->achievementLogs($this->alice, 'active_30')->count());
    }

    public function test_revoked_contributions_do_not_count_toward_progress(): void
    {
        $ids = $this->seed($this->alice, 'helpful', 5, 15);
        foreach (array_slice($ids, 0, 2) as $id) {
            CpLog::unguarded(fn () => CpLog::create([
                'user_id' => $this->alice->id, 'room_id' => $this->room->id, 'source' => 'revoke',
                'reference' => 'revoke:' . $id, 'base_amount' => 0, 'multiplier_pct' => 100,
                'amount' => -15, 'created_at' => now()->subDay(),
            ]));
        }

        $row = collect(app(AchievementService::class)->progress($this->alice->id))->firstWhere('key', 'helpful_10');

        $this->assertSame(3, $row['progress']);
        $this->assertFalse($row['unlocked']);
    }

    public function test_achievement_points_follow_admin_settings_and_zero_disables_it(): void
    {
        CpSetting::create(['key' => 'achievement_first_reply_points', 'value' => ['v' => 0]]);
        $question = $this->send($this->bob, 'Does anyone know the answer here?');

        $this->send($this->alice, 'Yes, it is the cache layer', $question->id);

        $this->assertSame(0, $this->achievementLogs($this->alice)->count());

        CpSetting::where('key', 'achievement_first_reply_points')->update(['value' => json_encode(['v' => 35])]);
        $this->send($this->alice, 'Another reply right after that', $question->id);

        $this->assertSame(35, $this->achievementLogs($this->alice, 'first_reply')->value('amount'));
    }

    public function test_event_multiplier_does_not_inflate_achievements(): void
    {
        CpEvent::create([
            'name' => 'Everything x2', 'multiplier_pct' => 200, 'sources' => null,
            'starts_at' => now()->subHour(), 'ends_at' => now()->addHour(), 'is_active' => true,
        ]);
        $question = $this->send($this->bob, 'Does anyone know the answer here?');

        $reply = $this->send($this->alice, 'Yes, it is the cache layer', $question->id);

        $this->assertSame(8, CpLog::where('reference', $reply->id)->where('source', 'reply')->value('amount'));
        $this->assertSame(20, $this->achievementLogs($this->alice, 'first_reply')->value('amount'));
    }

    public function test_progress_lists_every_achievement_with_defaults(): void
    {
        $rows = collect(app(AchievementService::class)->progress($this->alice->id));

        $this->assertCount(6, $rows);
        $first = $rows->firstWhere('key', 'first_reply');
        $this->assertSame(20, $first['points']);
        $this->assertSame(1, $first['threshold']);
        $this->assertSame(0, $first['progress']);
        $this->assertFalse($first['unlocked']);
        $this->assertNull($first['unlocked_at']);
    }

    public function test_penalty_deducts_once_floors_at_zero_and_keeps_the_ledger(): void
    {
        Member::where('user_id', $this->alice->id)->update(['xp_points' => 30]);

        $log = $this->service->applyPenalty($this->room->id, $this->alice->id, 10, 'report:abc');
        $this->assertSame(-10, $log->amount);
        $this->assertSame(20, (int) Member::where('user_id', $this->alice->id)->value('xp_points'));

        // Referensi yang sama tidak bisa menjatuhkan penalti dua kali.
        $this->assertNull($this->service->applyPenalty($this->room->id, $this->alice->id, 10, 'report:abc'));
        $this->assertSame(20, (int) Member::where('user_id', $this->alice->id)->value('xp_points'));

        // Penalti lebih besar dari saldo: saldo berhenti di 0, ledger mencatat angka penuh.
        $big = $this->service->applyPenalty($this->room->id, $this->alice->id, 50, 'report:def');
        $this->assertSame(-50, $big->amount);
        $this->assertSame(0, (int) Member::where('user_id', $this->alice->id)->value('xp_points'));
        Event::assertNotDispatched(ContributionAwarded::class, fn ($e) => $e->amount < 0);
    }

    public function test_zero_or_unknown_member_penalty_is_ignored(): void
    {
        $this->assertNull($this->service->applyPenalty($this->room->id, $this->alice->id, 0, 'report:none'));
        $this->assertNull($this->service->applyPenalty($this->room->id, User::factory()->create()->id, 10, 'report:ghost'));
        $this->assertSame(0, CpLog::where('source', 'penalty')->count());
    }
}
