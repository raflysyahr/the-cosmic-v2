<?php

namespace App\Modules\Discuss\tests\Feature;

use App\Modules\Auth\Models\User;
use App\Modules\Discuss\Events\ContributionAwarded;
use App\Modules\Discuss\Events\MessageMarked;
use App\Modules\Discuss\Models\Member;
use App\Modules\Discuss\Models\Message;
use App\Modules\Discuss\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/** Menembak route HTTP sungguhan untuk Helpful & Best Answer. */
class MarkControllerTest extends TestCase
{
    use RefreshDatabase;

    private Room $room;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake([ContributionAwarded::class, MessageMarked::class]);
        $this->room = Room::factory()->create();
    }

    private function member(string $role = 'member', $joinedAt = null): User
    {
        $user = User::factory()->create();
        Member::create([
            'room_id' => $this->room->id,
            'user_id' => $user->id,
            'role' => $role,
            'xp_points' => 0,
            'is_banned' => false,
            'joined_at' => $joinedAt ?? now()->subDays(2),
            'last_read_at' => now(),
        ]);

        return $user;
    }

    private function message(User $author, ?string $replyToId = null): Message
    {
        return Message::factory()->create([
            'room_id' => $this->room->id,
            'user_id' => $author->id,
            'reply_to_id' => $replyToId,
        ]);
    }

    private function url(Message $message, string $action): string
    {
        return "/api/rooms/{$this->room->slug}/messages/{$message->id}/{$action}";
    }

    public function test_helpful_toggles_on_then_off_and_pays_the_author_once(): void
    {
        $author = $this->member();
        $helper = $this->member();
        $message = $this->message($author);

        $this->actingAs($helper)->postJson($this->url($message, 'helpful'))
            ->assertOk()
            ->assertJsonPath('action', 'added')
            ->assertJsonPath('marks.helpful_count', 1)
            ->assertJsonPath('marks.helpful_user_ids.0', $helper->id);

        $this->assertDatabaseHas('discuss_message_marks', ['message_id' => $message->id, 'marked_by' => $helper->id, 'kind' => 'helpful']);
        $this->assertDatabaseHas('discuss_cp_logs', ['user_id' => $author->id, 'source' => 'helpful', 'amount' => 15]);
        $this->assertSame(15, (int) Member::where('user_id', $author->id)->value('xp_points'));

        $this->actingAs($helper)->postJson($this->url($message, 'helpful'))
            ->assertOk()
            ->assertJsonPath('action', 'removed')
            ->assertJsonPath('marks.helpful_count', 0);

        $this->assertDatabaseMissing('discuss_message_marks', ['message_id' => $message->id]);
        $this->assertSame(15, (int) Member::where('user_id', $author->id)->value('xp_points'));
        Event::assertDispatched(MessageMarked::class, 2);
    }

    public function test_rule_violations_come_back_as_422_with_a_readable_reason(): void
    {
        $author = $this->member();
        $message = $this->message($author);

        $this->actingAs($author)->postJson($this->url($message, 'helpful'))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['mark']);

        $fresh = $this->member('member', now()->subHour());
        $this->actingAs($fresh)->postJson($this->url($message, 'helpful'))
            ->assertStatus(422)
            ->assertJsonPath('errors.mark.0', 'You can mark messages as helpful after being a member for 24 hours.');

        $outsider = User::factory()->create();
        $this->actingAs($outsider)->postJson($this->url($message, 'helpful'))->assertStatus(422);

        $this->assertDatabaseCount('discuss_message_marks', 0);
    }

    public function test_marking_requires_login(): void
    {
        $message = $this->message($this->member());

        $this->postJson($this->url($message, 'helpful'))->assertStatus(401);
        $this->postJson($this->url($message, 'best-answer'))->assertStatus(401);
    }

    public function test_best_answer_flow_over_http_and_it_shows_up_in_the_message_list(): void
    {
        $asker = $this->member();
        $answerer = $this->member();
        $question = $this->message($asker);
        $answer = $this->message($answerer, $question->id);

        $this->actingAs($asker)->postJson($this->url($answer, 'best-answer'))
            ->assertOk()
            ->assertJsonPath('action', 'added')
            ->assertJsonPath('marks.is_best_answer', true);

        $this->assertDatabaseHas('discuss_cp_logs', ['user_id' => $answerer->id, 'source' => 'best_answer', 'amount' => 25]);

        $list = $this->actingAs($asker)->getJson("/api/rooms/{$this->room->slug}/messages")->assertOk();
        $row = collect($list->json('data'))->firstWhere('id', $answer->id);
        $this->assertTrue($row['marks']['is_best_answer']);
        $this->assertSame(0, $row['marks']['helpful_count']);

        $this->actingAs($asker)->postJson($this->url($answer, 'best-answer'))
            ->assertOk()
            ->assertJsonPath('action', 'removed');
        $this->assertSame(0, (int) Member::where('user_id', $answerer->id)->value('xp_points'));
    }

    public function test_only_the_asker_or_a_moderator_can_choose_the_best_answer(): void
    {
        $asker = $this->member();
        $answerer = $this->member();
        $bystander = $this->member();
        $moderator = $this->member('moderator');
        $question = $this->message($asker);
        $answer = $this->message($answerer, $question->id);

        $this->actingAs($bystander)->postJson($this->url($answer, 'best-answer'))->assertStatus(422);
        $this->actingAs($answerer)->postJson($this->url($answer, 'best-answer'))->assertStatus(422);
        $this->assertDatabaseCount('discuss_message_marks', 0);

        $this->actingAs($moderator)->postJson($this->url($answer, 'best-answer'))->assertOk();
        $this->assertDatabaseCount('discuss_message_marks', 1);
    }

    public function test_message_from_another_room_cannot_be_marked_through_this_room(): void
    {
        $author = $this->member();
        $helper = $this->member();
        $otherRoom = Room::factory()->create();
        $foreign = Message::factory()->create(['room_id' => $otherRoom->id, 'user_id' => $author->id]);

        $this->actingAs($helper)
            ->postJson("/api/rooms/{$this->room->slug}/messages/{$foreign->id}/helpful")
            ->assertStatus(422);

        $this->assertDatabaseCount('discuss_message_marks', 0);
    }
}
