<?php

namespace App\Modules\Discuss\Tests\Feature;

use App\Modules\Auth\Models\User;
use App\Modules\Discuss\Models\Emote;
use App\Modules\Discuss\Models\Member;
use App\Modules\Discuss\Models\Message;
use App\Modules\Discuss\Models\Reaction;
use App\Modules\Discuss\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReactionTest extends TestCase
{
    use RefreshDatabase;

    private function joinRoom(User $user, Room $room): void
    {
        Member::create([
            'room_id' => $room->id,
            'user_id' => $user->id,
            'role' => 'member',
            'is_banned' => false,
            'joined_at' => now(),
            'last_read_at' => now(),
        ]);
    }

    public function test_can_add_reaction_to_other_user_message(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $room = Room::factory()->create();
        $emote = Emote::create(['code' => ':like:', 'name' => 'Like', 'unicode' => '👍']);

        $this->joinRoom($user, $room);

        $message = Message::create([
            'room_id' => $room->id,
            'user_id' => $other->id,
            'type' => 'text',
            'body' => 'Test',
        ]);

        $response = $this->actingAs($user)->postJson(
            "/api/rooms/{$room->slug}/messages/{$message->id}/reactions",
            ['emote_id' => $emote->id],
        );

        $response->assertStatus(200);
        $this->assertDatabaseHas('discuss_reactions', [
            'message_id' => $message->id,
            'user_id' => $user->id,
            'emote_id' => $emote->id,
        ]);
    }

    public function test_cannot_react_to_own_message(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->create();
        $emote = Emote::create(['code' => ':like:', 'name' => 'Like', 'unicode' => '👍']);

        $this->joinRoom($user, $room);

        $message = Message::create([
            'room_id' => $room->id,
            'user_id' => $user->id,
            'type' => 'text',
            'body' => 'My message',
        ]);

        $response = $this->actingAs($user)->postJson(
            "/api/rooms/{$room->slug}/messages/{$message->id}/reactions",
            ['emote_id' => $emote->id],
        );

        $response->assertStatus(422);
        $this->assertDatabaseCount('discuss_reactions', 0);
    }

    public function test_same_reaction_twice_removes_it(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $room = Room::factory()->create();
        $emote = Emote::create(['code' => ':like:', 'name' => 'Like', 'unicode' => '👍']);

        $this->joinRoom($user, $room);

        $message = Message::create([
            'room_id' => $room->id,
            'user_id' => $other->id,
            'type' => 'text',
            'body' => 'Test',
        ]);

        // First reaction — adds
        $this->actingAs($user)->postJson(
            "/api/rooms/{$room->slug}/messages/{$message->id}/reactions",
            ['emote_id' => $emote->id],
        )->assertStatus(200);

        $this->assertDatabaseHas('discuss_reactions', [
            'message_id' => $message->id,
            'user_id' => $user->id,
            'emote_id' => $emote->id,
        ]);

        // Second reaction with same emote — removes
        $this->actingAs($user)->postJson(
            "/api/rooms/{$room->slug}/messages/{$message->id}/reactions",
            ['emote_id' => $emote->id],
        )->assertStatus(200);

        $this->assertDatabaseMissing('discuss_reactions', [
            'message_id' => $message->id,
            'user_id' => $user->id,
            'emote_id' => $emote->id,
        ]);
    }

    public function test_switching_reaction_replaces_old_emote(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $room = Room::factory()->create();
        $emoteA = Emote::create(['code' => ':like:', 'name' => 'Like', 'unicode' => '👍']);
        $emoteB = Emote::create(['code' => ':love:', 'name' => 'Love', 'unicode' => '❤️']);

        $this->joinRoom($user, $room);

        $message = Message::create([
            'room_id' => $room->id,
            'user_id' => $other->id,
            'type' => 'text',
            'body' => 'Test',
        ]);

        // React with emote A
        $this->actingAs($user)->postJson(
            "/api/rooms/{$room->slug}/messages/{$message->id}/reactions",
            ['emote_id' => $emoteA->id],
        )->assertStatus(200);

        $this->assertDatabaseHas('discuss_reactions', [
            'message_id' => $message->id,
            'user_id' => $user->id,
            'emote_id' => $emoteA->id,
        ]);

        // Switch to emote B
        $this->actingAs($user)->postJson(
            "/api/rooms/{$room->slug}/messages/{$message->id}/reactions",
            ['emote_id' => $emoteB->id],
        )->assertStatus(200);

        // Old emote removed, new emote present
        $this->assertDatabaseMissing('discuss_reactions', [
            'message_id' => $message->id,
            'user_id' => $user->id,
            'emote_id' => $emoteA->id,
        ]);
        $this->assertDatabaseHas('discuss_reactions', [
            'message_id' => $message->id,
            'user_id' => $user->id,
            'emote_id' => $emoteB->id,
        ]);
    }

    public function test_reactions_are_grouped_into_grouped_reaction_array(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $userC = User::factory()->create();
        $room = Room::factory()->create();
        $emote = Emote::create(['code' => ':like:', 'name' => 'Like', 'unicode' => '👍']);

        $this->joinRoom($userA, $room);
        $this->joinRoom($userB, $room);

        // Message from a third user
        $message = Message::create([
            'room_id' => $room->id,
            'user_id' => $userC->id,
            'type' => 'text',
            'body' => 'Hi',
        ]);

        // Two different users react with the same emote.
        Reaction::create(['message_id' => $message->id, 'user_id' => $userA->id, 'emote_id' => $emote->id]);
        Reaction::create(['message_id' => $message->id, 'user_id' => $userB->id, 'emote_id' => $emote->id]);

        $response = $this->actingAs($userA)->getJson("/api/rooms/{$room->slug}/messages?take=50");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => ['reactions' => [['emoteId', 'emoteCode', 'unicode', 'count', 'userIds']]],
            ],
        ]);

        $reactions = collect($response->json('data'))
            ->firstWhere('id', $message->id)['reactions'];

        $this->assertIsArray($reactions, 'reactions should be a list, not a map');
        $this->assertCount(1, $reactions);

        $grouped = $reactions[0];
        $this->assertSame($emote->id, $grouped['emoteId']);
        $this->assertSame(':like:', $grouped['emoteCode']);
        $this->assertSame('👍', $grouped['unicode']);
        $this->assertSame(2, $grouped['count']);
        $this->assertContains($userA->id, $grouped['userIds']);
        $this->assertContains($userB->id, $grouped['userIds']);
    }
}
