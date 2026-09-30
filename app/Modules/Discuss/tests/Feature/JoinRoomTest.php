<?php

namespace App\Modules\Discuss\tests\Feature;

use App\Modules\Auth\Models\User;
use App\Modules\Discuss\Models\Member;
use App\Modules\Discuss\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JoinRoomTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_join_public_room(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->create(['type' => 'public']);

        $response = $this->actingAs($user)->postJson("/api/rooms/{$room->slug}/join");

        $response->assertStatus(201);

        $this->assertDatabaseHas('discuss_members', [
            'room_id' => $room->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_cannot_join_private_room(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->create(['type' => 'private']);

        $response = $this->actingAs($user)->postJson("/api/rooms/{$room->slug}/join");

        $response->assertStatus(422);
    }

    public function test_cannot_join_invite_only_room_without_invite(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->create(['type' => 'invite_only']);

        $response = $this->actingAs($user)->postJson("/api/rooms/{$room->slug}/join");

        $response->assertStatus(422);

        $this->assertDatabaseMissing('discuss_members', [
            'room_id' => $room->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_cannot_join_when_banned(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->create(['type' => 'public']);

        Member::create([
            'room_id' => $room->id,
            'user_id' => $user->id,
            'role' => 'member',
            'is_banned' => true,
            'joined_at' => now(),
            'last_read_at' => now(),
        ]);

        $response = $this->actingAs($user)->postJson("/api/rooms/{$room->slug}/join");

        $response->assertStatus(422);
    }
}
