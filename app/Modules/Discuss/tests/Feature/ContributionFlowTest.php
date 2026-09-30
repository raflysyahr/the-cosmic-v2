<?php

namespace App\Modules\Discuss\tests\Feature;

use App\Modules\Auth\Models\User;
use App\Modules\Discuss\Events\ContributionAwarded;
use App\Modules\Discuss\Events\MessageDeleted;
use App\Modules\Discuss\Events\MessageSent;
use App\Modules\Discuss\Events\ReactionToggled;
use App\Modules\Discuss\Models\CpLog;
use App\Modules\Discuss\Models\Member;
use App\Modules\Discuss\Models\Message;
use App\Modules\Discuss\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/** Menembak route HTTP sungguhan: membuktikan CP jalan dari request → listener → ledger. */
class ContributionFlowTest extends TestCase
{
    use RefreshDatabase;

    private function addMember(Room $room, User $user): Member
    {
        return Member::create([
            'room_id' => $room->id,
            'user_id' => $user->id,
            'role' => 'member',
            'xp_points' => 0,
            'is_banned' => false,
            'joined_at' => now(),
            'last_read_at' => now(),
        ]);
    }

    public function test_discuss_listeners_are_registered(): void
    {
        $this->assertTrue(Event::hasListeners(MessageSent::class));
        $this->assertTrue(Event::hasListeners(ReactionToggled::class));
        $this->assertTrue(Event::hasListeners(MessageDeleted::class));
    }

    public function test_sending_a_message_over_http_awards_cp_and_notifies(): void
    {
        Event::fake([ContributionAwarded::class]);
        $user = User::factory()->create();
        $room = Room::factory()->create();
        $this->addMember($room, $user);

        $this->actingAs($user)
            ->postJson("/api/rooms/{$room->slug}/messages", ['body' => 'Hello from the API'])
            ->assertStatus(201);

        $this->assertDatabaseHas('discuss_members', ['room_id' => $room->id, 'user_id' => $user->id, 'xp_points' => 1]);
        $this->assertDatabaseHas('discuss_cp_logs', ['user_id' => $user->id, 'source' => 'message', 'amount' => 1]);
        Event::assertDispatched(ContributionAwarded::class, fn ($e) => $e->userId === $user->id && $e->amount === 1);
    }

    public function test_reacting_over_http_awards_the_message_author(): void
    {
        $author = User::factory()->create();
        $reactor = User::factory()->create();
        $room = Room::factory()->create();
        $this->addMember($room, $author);
        $this->addMember($room, $reactor);
        $message = Message::factory()->create(['room_id' => $room->id, 'user_id' => $author->id]);

        $this->actingAs($reactor)
            ->postJson("/api/rooms/{$room->slug}/messages/{$message->id}/reactions", ['emote_id' => 'emote-1'])
            ->assertOk();

        $this->assertDatabaseHas('discuss_cp_logs', ['user_id' => $author->id, 'source' => 'reaction_received', 'amount' => 2]);
        $this->assertSame(2, (int) Member::where('user_id', $author->id)->value('xp_points'));

        // Toggle off lalu on lagi: tidak ada CP tambahan.
        $this->actingAs($reactor)->postJson("/api/rooms/{$room->slug}/messages/{$message->id}/reactions", ['emote_id' => 'emote-1']);
        $this->actingAs($reactor)->postJson("/api/rooms/{$room->slug}/messages/{$message->id}/reactions", ['emote_id' => 'emote-1']);
        $this->assertSame(1, CpLog::where('source', 'reaction_received')->count());
        $this->assertSame(2, (int) Member::where('user_id', $author->id)->value('xp_points'));
    }

    public function test_deleting_a_message_over_http_revokes_points(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->create();
        $this->addMember($room, $user);

        $id = $this->actingAs($user)
            ->postJson("/api/rooms/{$room->slug}/messages", ['body' => 'Soon to be deleted'])
            ->json('message.id');
        $this->assertSame(1, (int) Member::where('user_id', $user->id)->value('xp_points'));

        $this->actingAs($user)->deleteJson("/api/rooms/{$room->slug}/messages/{$id}")->assertOk();

        $this->assertSame(0, (int) Member::where('user_id', $user->id)->value('xp_points'));
        $this->assertDatabaseHas('discuss_cp_logs', ['user_id' => $user->id, 'source' => 'revoke', 'amount' => -1]);
    }
}
