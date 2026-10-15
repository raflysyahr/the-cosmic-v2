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

    public function test_client_cannot_set_room_context(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/rooms', [
            'name' => 'Fake DM',
            'slug' => 'fake-dm',
            'type' => 'public',
            'context_type' => 'direct',
            'context_id' => 'someone_else',
        ])->assertStatus(422)->assertJsonValidationErrors(['context_type', 'context_id']);

        $this->assertDatabaseMissing('discuss_rooms', ['slug' => 'fake-dm']);
    }

    public function test_created_room_never_has_a_context(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/rooms', [
            'name' => 'Normal Room',
            'slug' => 'normal-room',
            'type' => 'public',
        ])->assertStatus(201);

        $room = Room::where('slug', 'normal-room')->first();
        $this->assertNull($room->context_type);
        $this->assertNull($room->context_id);
    }

    public function test_reserved_and_server_owned_slugs_are_rejected(): void
    {
        $user = User::factory()->create();

        $slugs = [
            'story', 'leaderboard', 'invite',          // bentrok dengan rute tetap
            'direct-abc123', 'comic-solo-leveling',    // prefix milik server
            'UPPER', 'has space', 'under_score', '-lead', 'trail-', 'a--b',
        ];

        foreach ($slugs as $slug) {
            $this->actingAs($user)->postJson('/api/rooms', [
                'name' => 'X',
                'slug' => $slug,
                'type' => 'public',
            ])->assertStatus(422)->assertJsonValidationErrors(['slug']);
        }

        $this->assertSame(0, Room::count());
    }

    public function test_settings_reject_unknown_keys(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/rooms', [
            'name' => 'Sneaky',
            'slug' => 'sneaky-room',
            'type' => 'public',
            'settings' => ['invite_link' => 'my-own-token', 'is_direct' => true],
        ])->assertStatus(422)->assertJsonValidationErrors(['settings']);
    }

    public function test_settings_values_are_bounded(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/rooms', [
            'name' => 'Huge',
            'slug' => 'huge-room',
            'type' => 'public',
            'settings' => ['max_members' => 99999, 'slow_mode_seconds' => -1],
        ])->assertStatus(422)->assertJsonValidationErrors(['settings.max_members', 'settings.slow_mode_seconds']);
    }

    public function test_known_settings_are_stored_on_top_of_defaults(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/rooms', [
            'name' => 'Slow',
            'slug' => 'slow-room',
            'type' => 'public',
            'settings' => ['slow_mode_seconds' => 10],
        ])->assertStatus(201);

        $settings = Room::where('slug', 'slow-room')->first()->settings;
        $this->assertSame(10, $settings['slow_mode_seconds']);
        $this->assertSame(500, $settings['max_members']);
    }
}
