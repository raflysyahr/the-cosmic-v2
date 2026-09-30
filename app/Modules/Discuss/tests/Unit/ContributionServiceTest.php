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
use App\Modules\Discuss\Models\Rank;
use App\Modules\Discuss\Models\Room;
use App\Modules\Discuss\Services\ContributionService;
use App\Modules\Discuss\Services\MessageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Semua pengiriman pesan di sini lewat MessageService::send() sungguhan,
 * jadi test ini sekaligus membuktikan listener AwardXpOnMessage terdaftar
 * dan benar-benar jalan (bukan hanya ContributionService dipanggil manual).
 */
class ContributionServiceTest extends TestCase
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

        // Hanya ContributionAwarded yang di-fake; event lain tetap jalan.
        Event::fake([ContributionAwarded::class]);

        $this->service = app(ContributionService::class);
        $this->messages = app(MessageService::class);
        $this->room = Room::factory()->create();
        $this->alice = User::factory()->create();
        $this->bob = User::factory()->create();

        $this->join($this->alice);
        $this->join($this->bob);
    }

    private function join(User $user, ?Room $room = null): Member
    {
        return Member::create([
            'room_id' => ($room ?? $this->room)->id,
            'user_id' => $user->id,
            'role' => 'member',
            'xp_points' => 0,
            'is_banned' => false,
            'joined_at' => now(),
            'last_read_at' => now(),
        ]);
    }

    private function send(User $user, ?string $body, ?string $replyToId = null, ?Room $room = null, string $type = 'text', array $attachments = []): Message
    {
        return $this->messages->send(new SendMessageData(
            roomId: ($room ?? $this->room)->id,
            userId: $user->id,
            replyToId: $replyToId,
            type: $type,
            body: $body,
            attachments: $attachments,
            metadata: [],
        ));
    }

    private function xp(User $user, ?Room $room = null): int
    {
        return (int) Member::where('room_id', ($room ?? $this->room)->id)
            ->where('user_id', $user->id)
            ->value('xp_points');
    }

    private function logFor(Message $message, ?string $userId = null): ?CpLog
    {
        return CpLog::where('reference', $message->id)
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->first();
    }

    private function seedLogs(User $user, string $source, int $count, int $base): void
    {
        for ($i = 0; $i < $count; $i++) {
            CpLog::create([
                'user_id' => $user->id,
                'room_id' => $this->room->id,
                'source' => $source,
                'reference' => "seed-{$source}-" . uniqid('', true),
                'base_amount' => $base,
                'multiplier_pct' => 100,
                'amount' => $base,
            ]);
        }
    }

    private function runningEvent(int $pct, array $overrides = []): CpEvent
    {
        return CpEvent::create(array_merge([
            'name' => 'Weekend Boost',
            'multiplier_pct' => $pct,
            'sources' => null,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
            'is_active' => true,
        ], $overrides));
    }

    public function test_plain_message_awards_base_points(): void
    {
        $message = $this->send($this->alice, 'Hello there everyone');

        $this->assertSame(1, $this->xp($this->alice));
        $log = $this->logFor($message);
        $this->assertSame('message', $log->source->value);
        $this->assertSame(1, $log->amount);

        Event::assertDispatched(
            ContributionAwarded::class,
            fn ($e) => $e->userId === $this->alice->id && $e->amount === 1 && $e->multiplierPct === 100,
        );
    }

    public function test_reply_awards_replier_and_parent_author(): void
    {
        $question = $this->send($this->bob, 'Anyone know how to fix X?');
        $reply = $this->send($this->alice, 'Try restarting it first', $question->id);

        $this->assertSame(4, $this->logFor($reply, $this->alice->id)->amount);
        $this->assertSame(4, $this->xp($this->alice));

        // Bob: 1 (pesannya sendiri) + 1 (reply_received).
        $this->assertSame(2, $this->xp($this->bob));
        $received = CpLog::where('user_id', $this->bob->id)->where('source', 'reply_received')->first();
        $this->assertSame($question->id, $received->subject_id);
    }

    public function test_reply_to_own_message_counts_as_plain_message(): void
    {
        $first = $this->send($this->alice, 'Starting a thought here');
        $this->send($this->alice, 'And continuing the thought', $first->id);

        $this->assertSame(2, $this->xp($this->alice));
        $this->assertSame(0, CpLog::where('source', 'reply')->count());
        $this->assertSame(0, CpLog::where('source', 'reply_received')->count());
    }

    public function test_reply_points_drop_after_daily_tiers(): void
    {
        $parent = $this->send($this->bob, 'Question for the room please');

        $this->seedLogs($this->alice, 'reply', 10, 4);
        $eleventh = $this->send($this->alice, 'Eleventh helpful reply', $parent->id);
        $this->assertSame(2, $this->logFor($eleventh, $this->alice->id)->amount);

        // Total sudah 11; tambah 9 lagi supaya reply berikutnya jadi #21.
        $this->seedLogs($this->alice, 'reply', 9, 2);
        $twentyFirst = $this->send($this->alice, 'Twenty first reply today', $parent->id);
        $this->assertNull($this->logFor($twentyFirst, $this->alice->id));
    }

    public function test_plain_message_daily_cap_stops_points(): void
    {
        $this->seedLogs($this->alice, 'message', 20, 1);

        $message = $this->send($this->alice, 'One message over the cap');

        $this->assertNull($this->logFor($message));
        $this->assertSame(0, $this->xp($this->alice));
    }

    public function test_duplicate_body_within_window_gives_no_points(): void
    {
        $this->send($this->alice, 'Exactly the same text');
        $second = $this->send($this->alice, 'Exactly the same text');

        $this->assertNull($this->logFor($second));
        $this->assertSame(1, $this->xp($this->alice));
    }

    public function test_emoji_only_message_gives_no_points_but_attachment_does(): void
    {
        $emoji = $this->send($this->alice, '🔥🔥');
        $this->assertNull($this->logFor($emoji));

        $image = $this->send($this->alice, null, null, null, 'image', ['https://example.com/pic.jpg']);
        $this->assertNotNull($this->logFor($image));
    }

    public function test_burst_of_messages_gives_no_points(): void
    {
        Message::factory()->count(10)->create([
            'room_id' => $this->room->id,
            'user_id' => $this->alice->id,
        ]);

        $eleventh = $this->send($this->alice, 'Eleventh message inside twenty seconds');

        $this->assertNull($this->logFor($eleventh));
    }

    public function test_event_multiplier_scales_payout_and_is_recorded(): void
    {
        $event = $this->runningEvent(200);

        $message = $this->send($this->alice, 'Posting during the event');

        $log = $this->logFor($message);
        $this->assertSame(1, $log->base_amount);
        $this->assertSame(200, $log->multiplier_pct);
        $this->assertSame(2, $log->amount);
        $this->assertSame($event->id, $log->event_id);
        $this->assertSame(2, $this->xp($this->alice));

        Event::assertDispatched(
            ContributionAwarded::class,
            fn ($e) => $e->amount === 2 && $e->multiplierPct === 200 && $e->eventName === 'Weekend Boost',
        );
    }

    public function test_expired_inactive_and_other_source_events_are_ignored(): void
    {
        $this->runningEvent(300, ['ends_at' => now()->subMinute()]);
        $this->runningEvent(300, ['is_active' => false]);
        $this->runningEvent(300, ['sources' => ['reply']]);
        $this->runningEvent(300, ['room_id' => Room::factory()->create()->id]);

        $message = $this->send($this->alice, 'No event applies to me');

        $this->assertSame(1, $this->logFor($message)->amount);
    }

    public function test_overlapping_events_use_largest_multiplier_without_stacking(): void
    {
        $this->runningEvent(200);
        $this->runningEvent(300, ['name' => 'Mega Boost']);

        $message = $this->send($this->alice, 'Two events overlap right now');

        $this->assertSame(3, $this->logFor($message)->amount);
    }

    public function test_caps_count_base_points_not_boosted_payout(): void
    {
        $this->runningEvent(200);
        // 19 poin dasar terpakai (dibayar x2 = 38), sisa kuota dasar: 1.
        CpLog::create([
            'user_id' => $this->alice->id,
            'room_id' => $this->room->id,
            'source' => 'message',
            'reference' => 'seed-boosted',
            'base_amount' => 19,
            'multiplier_pct' => 200,
            'amount' => 38,
        ]);

        $within = $this->send($this->alice, 'Last message inside the cap');
        $this->assertSame(2, $this->logFor($within)->amount);

        $over = $this->send($this->alice, 'First message past the cap');
        $this->assertNull($this->logFor($over));
    }

    public function test_reaction_awards_author_once_per_reactor(): void
    {
        $message = $this->send($this->bob, 'A post worth reacting to');

        $first = $this->service->awardForReaction($this->room->id, $message->id, $this->alice->id);
        $this->assertSame(2, $first->amount);
        $this->assertSame(3, $this->xp($this->bob));

        // Reaktor yang sama (ganti emote / toggle ulang) tidak menambah lagi.
        $this->assertNull($this->service->awardForReaction($this->room->id, $message->id, $this->alice->id));
        $this->assertSame(3, $this->xp($this->bob));
    }

    public function test_reaction_to_own_message_or_from_unverified_account_gives_nothing(): void
    {
        $message = $this->send($this->bob, 'A post worth reacting to');

        $this->assertNull($this->service->awardForReaction($this->room->id, $message->id, $this->bob->id));

        $sock = User::factory()->unverified()->create();
        $this->join($sock);
        $this->assertNull($this->service->awardForReaction($this->room->id, $message->id, $sock->id));

        $this->assertSame(1, $this->xp($this->bob));
    }

    public function test_reaction_reward_per_message_is_capped_by_admin_setting(): void
    {
        CpSetting::create(['key' => 'reaction_cap_per_message', 'value' => ['v' => 2]]);
        $message = $this->send($this->bob, 'Capped at one reaction reward');
        $carol = User::factory()->create();
        $this->join($carol);

        $this->assertNotNull($this->service->awardForReaction($this->room->id, $message->id, $this->alice->id));
        $this->assertNull($this->service->awardForReaction($this->room->id, $message->id, $carol->id));
    }

    public function test_reaction_recipient_is_promoted_when_points_reach_next_rank(): void
    {
        $newcomer = Rank::create(['room_id' => $this->room->id, 'name' => 'Newcomer', 'label_color' => '#666666', 'min_xp' => 0, 'order' => 1]);
        $regular = Rank::create(['room_id' => $this->room->id, 'name' => 'Regular', 'label_color' => '#22c55e', 'min_xp' => 3, 'order' => 2]);

        $message = $this->send($this->bob, 'Earning my first points');
        $this->assertSame($newcomer->id, Member::where('user_id', $this->bob->id)->value('rank_id'));

        // +2 dari reaksi → 3 poin. Bob tidak mengirim pesan lagi, jadi
        // promosinya harus dicek dari jalur reaksi, bukan listener pesan.
        $this->service->awardForReaction($this->room->id, $message->id, $this->alice->id);

        $this->assertSame($regular->id, Member::where('user_id', $this->bob->id)->value('rank_id'));
    }

    public function test_deleting_a_message_revokes_its_points_once(): void
    {
        $message = $this->send($this->bob, 'This will be removed soon');
        $this->service->awardForReaction($this->room->id, $message->id, $this->alice->id);
        $this->assertSame(3, $this->xp($this->bob));

        $this->messages->delete($message, $this->bob->id);

        $this->assertSame(0, $this->xp($this->bob));
        $this->assertSame(2, CpLog::where('source', 'revoke')->count());
        $this->assertSame(0, $this->service->revokeForMessage($message->fresh()));
        $this->assertSame(0, $this->xp($this->bob));
    }

    public function test_delete_and_repost_does_not_free_up_daily_quota(): void
    {
        $this->seedLogs($this->alice, 'message', 19, 1);
        $last = $this->send($this->alice, 'Twentieth message of the day');
        $this->messages->delete($last, $this->alice->id);

        $repost = $this->send($this->alice, 'Twentieth message again');

        $this->assertNull($this->logFor($repost));
    }

    public function test_admin_settings_override_defaults_and_can_disable_points(): void
    {
        CpSetting::create(['key' => 'message_points', 'value' => ['v' => 3]]);
        $boosted = $this->send($this->alice, 'Points are three now');
        $this->assertSame(3, $this->logFor($boosted)->amount);

        CpSetting::create(['key' => 'enabled', 'value' => ['v' => false]]);
        $off = $this->send($this->alice, 'Points are switched off');
        $this->assertNull($this->logFor($off));
    }

    public function test_room_opt_out_and_direct_rooms_give_no_points(): void
    {
        $quiet = Room::factory()->create(['settings' => ['xp_per_message' => 0]]);
        $direct = Room::factory()->create(['settings' => ['is_direct' => true, 'xp_per_message' => 5]]);
        $this->join($this->alice, $quiet);
        $this->join($this->alice, $direct);

        $this->assertNull($this->logFor($this->send($this->alice, 'Quiet room message', null, $quiet)));
        $this->assertNull($this->logFor($this->send($this->alice, 'Direct room message', null, $direct)));
        $this->assertSame(0, CpLog::count());
    }
}
