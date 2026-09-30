<?php

namespace App\Modules\Discuss\Tests\Feature;

use App\Modules\Auth\Models\User;
use App\Modules\Discuss\Models\Member;
use App\Modules\Discuss\Models\Message;
use App\Modules\Discuss\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PinnedMessageTest extends TestCase
{
    use RefreshDatabase;

    private function createRoomWithMember(string $role = 'member'): array
    {
        $user = User::factory()->create();
        $room = Room::factory()->create(['context_type' => 'comic']);
        Member::create([
            'room_id' => $room->id,
            'user_id' => $user->id,
            'role' => $role,
            'is_banned' => false,
            'joined_at' => now(),
            'last_read_at' => now(),
        ]);
        $message = Message::factory()->create([
            'room_id' => $room->id,
            'user_id' => $user->id,
        ]);

        return compact('user', 'room', 'message');
    }

    public function test_admin_can_pin_message(): void
    {
        ['user' => $user, 'room' => $room, 'message' => $message] = $this->createRoomWithMember('admin');

        $response = $this->actingAs($user)->postJson("/api/rooms/{$room->slug}/pin", [
            'message_id' => $message->id,
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['pinned' => [['id', 'body', 'user']]]);
    }

    public function test_moderator_cannot_pin_message(): void
    {
        ['user' => $user, 'room' => $room, 'message' => $message] = $this->createRoomWithMember('moderator');

        $response = $this->actingAs($user)->postJson("/api/rooms/{$room->slug}/pin", [
            'message_id' => $message->id,
        ]);

        $response->assertStatus(422);
    }

    public function test_member_cannot_pin_message(): void
    {
        ['user' => $user, 'room' => $room, 'message' => $message] = $this->createRoomWithMember('member');

        $response = $this->actingAs($user)->postJson("/api/rooms/{$room->slug}/pin", [
            'message_id' => $message->id,
        ]);

        $response->assertStatus(422);
    }

    public function test_non_member_cannot_pin_message(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->create(['context_type' => 'comic']);
        $message = Message::factory()->create(['room_id' => $room->id]);

        $response = $this->actingAs($user)->postJson("/api/rooms/{$room->slug}/pin", [
            'message_id' => $message->id,
        ]);

        $response->assertStatus(422);
    }

    public function test_can_unpin_specific_message(): void
    {
        ['user' => $user, 'room' => $room] = $this->createRoomWithMember('admin');
        $msg1 = Message::factory()->create(['room_id' => $room->id]);
        $msg2 = Message::factory()->create(['room_id' => $room->id]);

        // Pin both
        $this->actingAs($user)->postJson("/api/rooms/{$room->slug}/pin", ['message_id' => $msg1->id]);
        $this->actingAs($user)->postJson("/api/rooms/{$room->slug}/pin", ['message_id' => $msg2->id]);

        // Unpin msg1 only
        $response = $this->actingAs($user)->deleteJson("/api/rooms/{$room->slug}/pin", [
            'message_id' => $msg1->id,
        ]);

        $response->assertOk();
        $response->assertJsonCount(1, 'pinned');
        $response->assertJsonPath('pinned.0.id', $msg2->id);
    }

    public function test_cannot_pin_in_direct_chat(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->create(['context_type' => 'direct']);
        Member::create([
            'room_id' => $room->id,
            'user_id' => $user->id,
            'role' => 'admin',
            'is_banned' => false,
            'joined_at' => now(),
            'last_read_at' => now(),
        ]);
        $message = Message::factory()->create(['room_id' => $room->id]);

        $response = $this->actingAs($user)->postJson("/api/rooms/{$room->slug}/pin", [
            'message_id' => $message->id,
        ]);

        $response->assertStatus(422);
    }

    public function test_multiple_pins_accumulate(): void
    {
        ['user' => $user, 'room' => $room] = $this->createRoomWithMember('admin');
        $msg1 = Message::factory()->create(['room_id' => $room->id]);
        $msg2 = Message::factory()->create(['room_id' => $room->id]);

        $this->actingAs($user)->postJson("/api/rooms/{$room->slug}/pin", ['message_id' => $msg1->id]);
        $response = $this->actingAs($user)->postJson("/api/rooms/{$room->slug}/pin", ['message_id' => $msg2->id]);

        $response->assertOk();
        $response->assertJsonCount(2, 'pinned');
    }

    public function test_pin_duplicate_is_idempotent(): void
    {
        ['user' => $user, 'room' => $room, 'message' => $message] = $this->createRoomWithMember('admin');

        $this->actingAs($user)->postJson("/api/rooms/{$room->slug}/pin", ['message_id' => $message->id]);
        $response = $this->actingAs($user)->postJson("/api/rooms/{$room->slug}/pin", ['message_id' => $message->id]);

        $response->assertOk();
        $response->assertJsonCount(1, 'pinned');
    }
}
