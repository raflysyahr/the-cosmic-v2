<?php

namespace App\Modules\Discuss\tests\Feature;

use App\Modules\Auth\Models\User;
use App\Modules\Discuss\Models\Member;
use App\Modules\Discuss\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoomControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_list_rooms_with_member_count(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->create(['type' => 'public']);

        Member::create([
            'room_id' => $room->id,
            'user_id' => $user->id,
            'role' => 'member',
            'is_banned' => false,
            'joined_at' => now(),
            'last_read_at' => now(),
        ]);

        $response = $this->actingAs($user)->getJson('/api/rooms');

        $response->assertStatus(200);

        $roomData = collect($response->json('data'))->firstWhere('id', $room->id);
        $this->assertNotNull($roomData);
        $this->assertEquals(1, $roomData['member_count']);
    }

    public function test_can_show_room_with_member_count(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->create(['type' => 'public']);

        Member::create([
            'room_id' => $room->id,
            'user_id' => $user->id,
            'role' => 'admin',
            'is_banned' => false,
            'joined_at' => now(),
            'last_read_at' => now(),
        ]);

        $response = $this->actingAs($user)->getJson("/api/rooms/{$room->slug}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.member_count', 1);
        $response->assertJsonPath('data.slug', $room->slug);
    }

    public function test_can_create_room(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/rooms', [
            'name' => 'Test Room',
            'slug' => 'test-room-' . uniqid(),
            'type' => 'public',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('room.member_count', 1);
    }
}
