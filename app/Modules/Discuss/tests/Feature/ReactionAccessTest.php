<?php

namespace App\Modules\Discuss\tests\Feature;

use App\Modules\Auth\Models\User;
use App\Modules\Discuss\Models\Member;
use App\Modules\Discuss\Models\Message;
use App\Modules\Discuss\Models\Room;
use App\Modules\Discuss\Services\RoomService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReactionAccessTest extends TestCase
{
    use RefreshDatabase;

    private function addMember(Room $room, User $user, bool $banned = false): void
    {
        Member::create([
            'room_id' => $room->id,
            'user_id' => $user->id,
            'role' => 'member',
            'is_banned' => $banned,
            'joined_at' => now(),
            'last_read_at' => now(),
        ]);
    }

    private function react(User $user, Room $room, string $messageId)
    {
        return $this->actingAs($user)->postJson(
            "/api/rooms/{$room->slug}/messages/{$messageId}/reactions",
            ['emote_id' => 'emote-1'],
        );
    }

    public function test_active_member_can_react(): void
    {
        $reactor = User::factory()->create();
        $room = Room::factory()->create(['type' => 'public']);
        $this->addMember($room, $reactor);
        $message = Message::factory()->create(['room_id' => $room->id]);

        $this->react($reactor, $room, $message->id)->assertOk();

        $this->assertDatabaseHas('discuss_reactions', ['message_id' => $message->id, 'user_id' => $reactor->id]);
    }

    public function test_non_member_cannot_react_in_a_public_room(): void
    {
        $outsider = User::factory()->create();
        $room = Room::factory()->create(['type' => 'public']);
        $message = Message::factory()->create(['room_id' => $room->id]);

        $this->react($outsider, $room, $message->id)->assertUnprocessable();

        $this->assertDatabaseCount('discuss_reactions', 0);
    }

    public function test_non_member_cannot_react_in_a_private_room(): void
    {
        $outsider = User::factory()->create();
        $room = Room::factory()->create(['type' => 'private']);
        $message = Message::factory()->create(['room_id' => $room->id]);

        $this->react($outsider, $room, $message->id)->assertUnprocessable();

        $this->assertDatabaseCount('discuss_reactions', 0);
    }

    public function test_third_party_cannot_react_inside_a_direct_chat(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $eve = User::factory()->create();
        $dm = app(RoomService::class)->findOrCreateDirectChat($alice->id, $bob->id);
        $message = Message::factory()->create(['room_id' => $dm->id, 'user_id' => $alice->id]);

        $this->react($eve, $dm, $message->id)->assertUnprocessable();
        $this->react($bob, $dm, $message->id)->assertOk();

        $this->assertDatabaseMissing('discuss_reactions', ['user_id' => $eve->id]);
    }

    public function test_banned_member_cannot_react(): void
    {
        $banned = User::factory()->create();
        $room = Room::factory()->create(['type' => 'public']);
        $this->addMember($room, $banned, banned: true);
        $message = Message::factory()->create(['room_id' => $room->id]);

        $this->react($banned, $room, $message->id)->assertUnprocessable();

        $this->assertDatabaseCount('discuss_reactions', 0);
    }

    public function test_non_member_gets_the_same_response_whether_or_not_the_message_exists(): void
    {
        $outsider = User::factory()->create();
        $room = Room::factory()->create(['type' => 'private']);
        $message = Message::factory()->create(['room_id' => $room->id]);

        $real = $this->react($outsider, $room, $message->id);
        $fake = $this->react($outsider, $room, '01ZZZZZZZZZZZZZZZZZZZZZZZZ');

        $real->assertUnprocessable();
        $fake->assertUnprocessable();
        $this->assertSame($real->json('errors'), $fake->json('errors'));
    }

    public function test_cannot_react_to_a_deleted_message(): void
    {
        $reactor = User::factory()->create();
        $room = Room::factory()->create(['type' => 'public']);
        $this->addMember($room, $reactor);
        $message = Message::factory()->create(['room_id' => $room->id, 'is_deleted' => true]);

        $this->react($reactor, $room, $message->id)->assertUnprocessable();

        $this->assertDatabaseCount('discuss_reactions', 0);
    }

    public function test_cannot_react_to_a_message_from_another_room_through_this_rooms_url(): void
    {
        $reactor = User::factory()->create();
        $roomA = Room::factory()->create(['type' => 'public']);
        $roomB = Room::factory()->create(['type' => 'public']);
        $this->addMember($roomA, $reactor);
        $message = Message::factory()->create(['room_id' => $roomB->id]);

        $this->react($reactor, $roomA, $message->id)->assertUnprocessable();

        $this->assertDatabaseCount('discuss_reactions', 0);
    }
}
