<?php

namespace Tests\Feature\Discuss;

use App\Modules\Discuss\Models\Member;
use App\Modules\Discuss\Models\Message;
use App\Modules\Discuss\Models\Room;
use App\Modules\Auth\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    // ── RoomController::index — private rooms must not leak ──

    public function test_non_member_cannot_see_private_rooms_in_listing(): void
    {
        $user = User::factory()->create();
        $private = Room::factory()->create(['type' => 'private']);

        $response = $this->actingAs($user)->getJson('/api/rooms');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertNotContains($private->id, $ids);
    }

    public function test_member_can_see_their_private_rooms_in_listing(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->create(['type' => 'private']);

        Member::create([
            'room_id' => $room->id, 'user_id' => $user->id,
            'role' => 'member', 'is_banned' => false,
            'joined_at' => now(), 'last_read_at' => now(),
        ]);

        $response = $this->actingAs($user)->getJson('/api/rooms');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($room->id, $ids);
    }

    public function test_public_rooms_always_visible_in_listing(): void
    {
        $user = User::factory()->create();
        $public = Room::factory()->create(['type' => 'public']);

        $response = $this->actingAs($user)->getJson('/api/rooms');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($public->id, $ids);
    }

    // ── RoomController::show — membership check for private rooms ──

    public function test_non_member_cannot_view_private_room_via_api(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->create(['type' => 'private']);

        $this->actingAs($user)->getJson("/api/rooms/{$room->slug}")
            ->assertForbidden();
    }

    public function test_member_can_view_private_room_via_api(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->create(['type' => 'private']);

        Member::create([
            'room_id' => $room->id, 'user_id' => $user->id,
            'role' => 'member', 'is_banned' => false,
            'joined_at' => now(), 'last_read_at' => now(),
        ]);

        $this->actingAs($user)->getJson("/api/rooms/{$room->slug}")
            ->assertOk();
    }

    public function test_any_user_can_view_public_room_via_api(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->create(['type' => 'public']);

        $this->actingAs($user)->getJson("/api/rooms/{$room->slug}")
            ->assertOk();
    }

    // ── RoomController::update — requires moderator/admin ──

    public function test_regular_member_cannot_update_room(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->create(['type' => 'public']);

        Member::create([
            'room_id' => $room->id, 'user_id' => $user->id,
            'role' => 'member', 'is_banned' => false,
            'joined_at' => now(), 'last_read_at' => now(),
        ]);

        $this->actingAs($user)->putJson("/api/rooms/{$room->slug}", [
            'name' => 'Hacked Room',
        ])->assertUnprocessable();
    }

    public function test_admin_can_update_room(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->create(['type' => 'public']);

        Member::create([
            'room_id' => $room->id, 'user_id' => $user->id,
            'role' => 'admin', 'is_banned' => false,
            'joined_at' => now(), 'last_read_at' => now(),
        ]);

        $this->actingAs($user)->putJson("/api/rooms/{$room->slug}", [
            'name' => 'Updated Room',
        ])->assertOk();

        $this->assertDatabaseHas('discuss_rooms', [
            'id' => $room->id, 'name' => 'Updated Room',
        ]);
    }

    public function test_stranger_cannot_update_room(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->create(['type' => 'public']);

        $this->actingAs($user)->putJson("/api/rooms/{$room->slug}", [
            'name' => 'Hacked Room',
        ])->assertUnprocessable();
    }

    // ── RoomController::destroy — requires moderator/admin ──

    public function test_regular_member_cannot_archive_room(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->create(['type' => 'public', 'is_active' => true]);

        Member::create([
            'room_id' => $room->id, 'user_id' => $user->id,
            'role' => 'member', 'is_banned' => false,
            'joined_at' => now(), 'last_read_at' => now(),
        ]);

        $this->actingAs($user)->deleteJson("/api/rooms/{$room->slug}")
            ->assertUnprocessable();

        $this->assertTrue($room->fresh()->is_active);
    }

    public function test_admin_can_archive_room(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->create(['type' => 'public', 'is_active' => true]);

        Member::create([
            'room_id' => $room->id, 'user_id' => $user->id,
            'role' => 'admin', 'is_banned' => false,
            'joined_at' => now(), 'last_read_at' => now(),
        ]);

        $this->actingAs($user)->deleteJson("/api/rooms/{$room->slug}")
            ->assertOk();

        $this->assertFalse($room->fresh()->is_active);
    }

    // ── MessageController::index — membership check for private rooms ──

    public function test_non_member_cannot_read_private_room_messages(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->create(['type' => 'private']);

        $this->actingAs($user)->getJson("/api/rooms/{$room->slug}/messages")
            ->assertForbidden();
    }

    public function test_member_can_read_private_room_messages(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->create(['type' => 'private']);

        Member::create([
            'room_id' => $room->id, 'user_id' => $user->id,
            'role' => 'member', 'is_banned' => false,
            'joined_at' => now(), 'last_read_at' => now(),
        ]);

        $this->actingAs($user)->getJson("/api/rooms/{$room->slug}/messages")
            ->assertOk();
    }

    public function test_any_user_can_read_public_room_messages(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->create(['type' => 'public']);

        $this->actingAs($user)->getJson("/api/rooms/{$room->slug}/messages")
            ->assertOk();
    }
}
