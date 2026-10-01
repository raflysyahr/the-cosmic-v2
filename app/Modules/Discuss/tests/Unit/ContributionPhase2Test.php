<?php

namespace App\Modules\Discuss\tests\Unit;

use App\Modules\Auth\Models\User;
use App\Modules\Discuss\Data\SendMessageData;
use App\Modules\Discuss\Events\ContributionAwarded;
use App\Modules\Discuss\Events\MessageMarked;
use App\Modules\Discuss\Models\CpEvent;
use App\Modules\Discuss\Models\CpLog;
use App\Modules\Discuss\Models\CpSetting;
use App\Modules\Discuss\Models\Member;
use App\Modules\Discuss\Models\Message;
use App\Modules\Discuss\Models\MessageMark;
use App\Modules\Discuss\Models\Room;
use App\Modules\Discuss\Services\MarkService;
use App\Modules\Discuss\Services\MessageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Fase 2: Helpful, Best Answer, bonus harian, streak. Pesan dikirim lewat
 * MessageService::send() sungguhan (listener ikut jalan).
 */
class ContributionPhase2Test extends TestCase
{
    use RefreshDatabase;

    private MarkService $marks;
    private MessageService $messages;
    private Room $room;
    private User $asker;
    private User $answerer;
    private User $helper;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake([ContributionAwarded::class, MessageMarked::class]);

        // Achievement (Fase 3) dimatikan supaya angka XP test Fase 2 tetap
        // murni; perilakunya dites di ContributionPhase3Test.
        foreach (['first_reply', 'replies_100', 'likes_100', 'helpful_10', 'best_answer_10', 'active_30'] as $key) {
            config(["discuss_cp.achievement_{$key}_points" => 0]);
        }

        $this->marks = app(MarkService::class);
        $this->messages = app(MessageService::class);
        $this->room = Room::factory()->create();
        $this->asker = User::factory()->create();
        $this->answerer = User::factory()->create();
        $this->helper = User::factory()->create();

        foreach ([$this->asker, $this->answerer, $this->helper] as $user) {
            $this->join($user);
        }
    }

    private function join(User $user, string $role = 'member', $joinedAt = null, ?Room $room = null): Member
    {
        return Member::create([
            'room_id' => ($room ?? $this->room)->id,
            'user_id' => $user->id,
            'role' => $role,
            'xp_points' => 0,
            'is_banned' => false,
            'joined_at' => $joinedAt ?? now()->subDays(2),
            'last_read_at' => now(),
        ]);
    }

    private function send(User $user, string $body, ?string $replyToId = null): Message
    {
        return $this->messages->send(new SendMessageData(
            roomId: $this->room->id,
            userId: $user->id,
            replyToId: $replyToId,
            type: 'text',
            body: $body,
            attachments: [],
            metadata: [],
        ));
    }

    private function xp(User $user): int
    {
        return (int) Member::where('room_id', $this->room->id)->where('user_id', $user->id)->value('xp_points');
    }

    private function logs(string $source, ?User $user = null)
    {
        return CpLog::where('source', $source)->when($user, fn ($q) => $q->where('user_id', $user->id));
    }

    private function assertRejected(callable $action, string $messageFragment = ''): void
    {
        try {
            $action();
            $this->fail('Expected a ValidationException.');
        } catch (ValidationException $e) {
            if ($messageFragment !== '') {
                $this->assertStringContainsString($messageFragment, $e->errors()['mark'][0]);
            }
        }
    }

    private function seedBonusDay(User $user, int $daysAgo): void
    {
        CpLog::create([
            'user_id' => $user->id,
            'room_id' => $this->room->id,
            'source' => 'daily_bonus',
            'reference' => now()->subDays($daysAgo)->toDateString(),
            'base_amount' => 10,
            'multiplier_pct' => 100,
            'amount' => 10,
        ]);
    }

    // ---------------------------------------------------------------- Helpful

    public function test_helpful_awards_author_once_per_message(): void
    {
        $message = $this->send($this->answerer, 'Restart the service and clear the cache');
        $before = $this->xp($this->answerer);

        $result = $this->marks->toggleHelpful($this->room->id, $message->id, $this->helper->id);

        $this->assertSame('added', $result['action']);
        $this->assertSame(1, $result['marks']['helpful_count']);
        $this->assertSame($before + 15, $this->xp($this->answerer));
        $this->assertSame(15, $this->logs('helpful', $this->answerer)->value('amount'));

        // Penanda kedua menambah hitungan, bukan CP.
        $second = $this->join(User::factory()->create());
        $this->marks->toggleHelpful($this->room->id, $message->id, $second->user_id);
        $this->assertSame($before + 15, $this->xp($this->answerer));
        $this->assertSame(1, $this->logs('helpful')->count());

        Event::assertDispatched(MessageMarked::class, fn ($e) => $e->kind === 'helpful' && $e->action === 'added');
    }

    public function test_unmarking_helpful_keeps_points_and_remarking_does_not_pay_again(): void
    {
        $message = $this->send($this->answerer, 'Restart the service and clear the cache');
        $this->marks->toggleHelpful($this->room->id, $message->id, $this->helper->id);
        $afterFirst = $this->xp($this->answerer);

        $removed = $this->marks->toggleHelpful($this->room->id, $message->id, $this->helper->id);
        $this->assertSame('removed', $removed['action']);
        $this->assertSame(0, $removed['marks']['helpful_count']);
        $this->assertSame($afterFirst, $this->xp($this->answerer));

        $this->marks->toggleHelpful($this->room->id, $message->id, $this->helper->id);
        $this->assertSame($afterFirst, $this->xp($this->answerer));
        $this->assertSame(1, $this->logs('helpful')->count());
    }

    public function test_helpful_rules_are_enforced_on_the_server(): void
    {
        $message = $this->send($this->answerer, 'Restart the service and clear the cache');

        // Pesan sendiri.
        $this->assertRejected(fn () => $this->marks->toggleHelpful($this->room->id, $message->id, $this->answerer->id), 'own message');

        // Bukan member.
        $outsider = User::factory()->create();
        $this->assertRejected(fn () => $this->marks->toggleHelpful($this->room->id, $message->id, $outsider->id), 'member');

        // Email belum terverifikasi.
        $unverified = User::factory()->unverified()->create();
        $this->join($unverified);
        $this->assertRejected(fn () => $this->marks->toggleHelpful($this->room->id, $message->id, $unverified->id), 'email');

        // Member baru (< 24 jam).
        $fresh = User::factory()->create();
        $this->join($fresh, 'member', now()->subHour());
        $this->assertRejected(fn () => $this->marks->toggleHelpful($this->room->id, $message->id, $fresh->id), '24 hours');

        // Dibanned.
        $banned = User::factory()->create();
        $this->join($banned)->update(['is_banned' => true]);
        $this->assertRejected(fn () => $this->marks->toggleHelpful($this->room->id, $message->id, $banned->id), 'member');

        $this->assertSame(0, MessageMark::count());
        $this->assertSame(0, $this->logs('helpful')->count());
    }

    public function test_helpful_on_deleted_or_system_or_foreign_room_message_is_rejected(): void
    {
        $deleted = $this->send($this->answerer, 'This one will disappear soon');
        $this->messages->delete($deleted, $this->answerer->id);
        $this->assertRejected(fn () => $this->marks->toggleHelpful($this->room->id, $deleted->id, $this->helper->id), 'not found');

        $system = Message::factory()->create(['room_id' => $this->room->id, 'user_id' => $this->answerer->id, 'type' => 'system']);
        $this->assertRejected(fn () => $this->marks->toggleHelpful($this->room->id, $system->id, $this->helper->id), 'System');

        $otherRoom = Room::factory()->create();
        $foreign = Message::factory()->create(['room_id' => $otherRoom->id, 'user_id' => $this->answerer->id]);
        $this->assertRejected(fn () => $this->marks->toggleHelpful($this->room->id, $foreign->id, $this->helper->id), 'not found');
    }

    public function test_helpful_daily_give_limit_is_admin_configurable(): void
    {
        CpSetting::create(['key' => 'helpful_daily_give_limit', 'value' => ['v' => 1]]);
        $first = $this->send($this->answerer, 'A genuinely useful explanation');
        $second = $this->send($this->answerer, 'Another genuinely useful tip');

        $this->marks->toggleHelpful($this->room->id, $first->id, $this->helper->id);

        $this->assertRejected(fn () => $this->marks->toggleHelpful($this->room->id, $second->id, $this->helper->id), 'limit');
    }

    public function test_helpful_points_follow_admin_settings_and_events(): void
    {
        CpSetting::create(['key' => 'helpful_points' , 'value' => ['v' => 20]]);
        CpEvent::create([
            'name' => 'Helpful Week', 'multiplier_pct' => 200, 'sources' => ['helpful'],
            'starts_at' => now()->subHour(), 'ends_at' => now()->addHour(), 'is_active' => true,
        ]);
        $message = $this->send($this->answerer, 'Something helpful during the event');

        $this->marks->toggleHelpful($this->room->id, $message->id, $this->helper->id);

        $log = $this->logs('helpful', $this->answerer)->first();
        $this->assertSame(20, $log->base_amount);
        $this->assertSame(40, $log->amount);
    }

    public function test_deleting_a_helpful_message_revokes_its_points(): void
    {
        $message = $this->send($this->answerer, 'Helpful but later removed');
        $this->marks->toggleHelpful($this->room->id, $message->id, $this->helper->id);
        $this->assertGreaterThanOrEqual(15, $this->xp($this->answerer));

        $this->messages->delete($message, $this->answerer->id);

        $this->assertSame(0, $this->xp($this->answerer));
        $this->assertSame(0, (int) CpLog::where('user_id', $this->answerer->id)->sum('amount'));
    }

    // ------------------------------------------------------------ Best Answer

    private function qa(): array
    {
        $question = $this->send($this->asker, 'How do I fix the login loop?');
        $answer = $this->send($this->answerer, 'Clear the session cookie first', $question->id);

        return [$question, $answer];
    }

    public function test_asker_can_mark_best_answer_and_author_is_paid(): void
    {
        [, $answer] = $this->qa();
        $before = $this->xp($this->answerer);

        $result = $this->marks->toggleBestAnswer($this->room->id, $answer->id, $this->asker->id);

        $this->assertSame('added', $result['action']);
        $this->assertTrue($result['marks']['is_best_answer']);
        $this->assertSame($before + 25, $this->xp($this->answerer));
        $this->assertSame(25, $this->logs('best_answer', $this->answerer)->value('amount'));
    }

    public function test_moderator_can_mark_best_answer_but_a_stranger_cannot(): void
    {
        [, $answer] = $this->qa();

        $this->assertRejected(fn () => $this->marks->toggleBestAnswer($this->room->id, $answer->id, $this->helper->id), 'asked');
        $this->assertSame(0, MessageMark::count());

        $mod = User::factory()->create();
        $this->join($mod, 'moderator');
        $this->marks->toggleBestAnswer($this->room->id, $answer->id, $mod->id);

        $this->assertSame(1, $this->logs('best_answer')->count());
    }

    public function test_best_answer_rejects_non_replies_and_self_answers(): void
    {
        [$question, $answer] = $this->qa();

        // Bukan reply.
        $this->assertRejected(fn () => $this->marks->toggleBestAnswer($this->room->id, $question->id, $this->asker->id), 'reply');

        // Penanya menjawab pertanyaannya sendiri.
        $selfReply = $this->send($this->asker, 'Never mind, I solved it myself', $question->id);
        $this->assertRejected(fn () => $this->marks->toggleBestAnswer($this->room->id, $selfReply->id, $this->asker->id), 'own answer');

        // Moderator yang menjawab lalu memilih jawabannya sendiri.
        $mod = User::factory()->create();
        $this->join($mod, 'moderator');
        $modAnswer = $this->send($mod, 'Moderator answer to the question', $question->id);
        $this->assertRejected(fn () => $this->marks->toggleBestAnswer($this->room->id, $modAnswer->id, $mod->id), 'own answer');

        $this->assertSame(0, MessageMark::where('kind', 'best_answer')->count());
    }

    public function test_only_one_best_answer_per_question_until_the_first_is_removed(): void
    {
        [$question, $first] = $this->qa();
        $other = $this->send($this->helper, 'Or reinstall the package entirely', $question->id);

        $this->marks->toggleBestAnswer($this->room->id, $first->id, $this->asker->id);
        $this->assertRejected(fn () => $this->marks->toggleBestAnswer($this->room->id, $other->id, $this->asker->id), 'already has a best answer');

        $this->marks->toggleBestAnswer($this->room->id, $first->id, $this->asker->id);
        $result = $this->marks->toggleBestAnswer($this->room->id, $other->id, $this->asker->id);

        $this->assertSame('added', $result['action']);
        $this->assertSame(1, MessageMark::where('kind', 'best_answer')->count());
    }

    public function test_removing_best_answer_takes_the_points_back_and_remarking_does_not_repay(): void
    {
        [, $answer] = $this->qa();
        $base = $this->xp($this->answerer);

        $this->marks->toggleBestAnswer($this->room->id, $answer->id, $this->asker->id);
        $this->assertSame($base + 25, $this->xp($this->answerer));

        $removed = $this->marks->toggleBestAnswer($this->room->id, $answer->id, $this->asker->id);
        $this->assertSame('removed', $removed['action']);
        $this->assertFalse($removed['marks']['is_best_answer']);
        $this->assertSame($base, $this->xp($this->answerer));
        $this->assertSame(1, $this->logs('revoke', $this->answerer)->count());

        $this->marks->toggleBestAnswer($this->room->id, $answer->id, $this->asker->id);
        $this->assertSame($base, $this->xp($this->answerer));
    }

    // ------------------------------------------------------------ Daily bonus

    public function test_first_valid_reply_of_the_day_adds_one_daily_bonus(): void
    {
        $question = $this->send($this->asker, 'Anyone seen this error before?');

        $first = $this->send($this->answerer, 'Yes, it is a cache problem', $question->id);
        $this->assertSame(4 + 10, $this->xp($this->answerer));
        $bonus = $this->logs('daily_bonus', $this->answerer)->first();
        $this->assertSame(now()->toDateString(), $bonus->reference);

        $this->send($this->answerer, 'Clear it with artisan optimize', $question->id);
        $this->assertSame(1, $this->logs('daily_bonus', $this->answerer)->count());
        $this->assertSame(14 + 4, $this->xp($this->answerer));

        Event::assertDispatched(ContributionAwarded::class, fn ($e) => $e->source === 'daily_bonus' && $e->amount === 10);
    }

    public function test_enough_plain_messages_also_qualify_for_the_daily_bonus(): void
    {
        foreach (['one', 'two', 'three', 'four'] as $word) {
            $this->send($this->answerer, "Message number {$word} today");
        }
        $this->assertSame(0, $this->logs('daily_bonus')->count());

        $this->send($this->answerer, 'Message number five today');

        $this->assertSame(1, $this->logs('daily_bonus', $this->answerer)->count());
        $this->assertSame(5 + 10, $this->xp($this->answerer));
    }

    public function test_messages_that_earn_nothing_do_not_qualify_for_the_bonus(): void
    {
        $question = $this->send($this->asker, 'Anyone seen this error before?');

        $this->send($this->answerer, '🔥🔥', $question->id);

        $this->assertSame(0, $this->logs('daily_bonus')->count());
    }

    public function test_daily_bonus_can_be_disabled_by_admin(): void
    {
        CpSetting::create(['key' => 'daily_bonus_points', 'value' => ['v' => 0]]);
        $question = $this->send($this->asker, 'Anyone seen this error before?');

        $this->send($this->answerer, 'Yes, it is a cache problem', $question->id);

        $this->assertSame(0, $this->logs('daily_bonus')->count());
        $this->assertSame(4, $this->xp($this->answerer));
    }

    // ----------------------------------------------------------------- Streak

    public function test_three_consecutive_days_pay_the_three_day_streak_bonus(): void
    {
        $this->seedBonusDay($this->answerer, 2);
        $this->seedBonusDay($this->answerer, 1);
        $question = $this->send($this->asker, 'Anyone seen this error before?');

        $this->send($this->answerer, 'Yes, it is a cache problem', $question->id);

        $streak = $this->logs('streak', $this->answerer)->first();
        $this->assertNotNull($streak);
        $this->assertSame(10, $streak->amount);
        $this->assertSame('3:' . now()->subDays(2)->toDateString(), $streak->reference);
        $this->assertSame(4 + 10 + 10, $this->xp($this->answerer));

        Event::assertDispatched(ContributionAwarded::class, fn ($e) => $e->source === 'streak' && $e->amount === 10);
    }

    public function test_seven_day_streak_pays_its_own_milestone(): void
    {
        foreach (range(1, 6) as $daysAgo) {
            $this->seedBonusDay($this->answerer, $daysAgo);
        }
        $question = $this->send($this->asker, 'Anyone seen this error before?');

        $this->send($this->answerer, 'Yes, it is a cache problem', $question->id);

        $this->assertSame(25, $this->logs('streak', $this->answerer)->value('amount'));
    }

    public function test_a_gap_breaks_the_streak(): void
    {
        // Kemarin kosong; dua hari lalu dan tiga hari lalu ada.
        $this->seedBonusDay($this->answerer, 2);
        $this->seedBonusDay($this->answerer, 3);
        $question = $this->send($this->asker, 'Anyone seen this error before?');

        $this->send($this->answerer, 'Yes, it is a cache problem', $question->id);

        $this->assertSame(1, $this->logs('daily_bonus', $this->answerer)->where('reference', now()->toDateString())->count());
        $this->assertSame(0, $this->logs('streak')->count());
    }

    public function test_streak_points_follow_admin_settings(): void
    {
        CpSetting::create(['key' => 'streak_3_points', 'value' => ['v' => 0]]);
        $this->seedBonusDay($this->answerer, 2);
        $this->seedBonusDay($this->answerer, 1);
        $question = $this->send($this->asker, 'Anyone seen this error before?');

        $this->send($this->answerer, 'Yes, it is a cache problem', $question->id);

        $this->assertSame(0, $this->logs('streak')->count());
    }
}
