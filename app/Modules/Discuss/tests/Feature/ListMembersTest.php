<?php

namespace App\Modules\Discuss\tests\Feature;

use App\Modules\Auth\Models\User;
use App\Modules\Discuss\Models\Member;
use App\Modules\Discuss\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListMembersTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_list_members_of_a_room(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $room = Room::factory()->create(['type' => 'public']);

        Member::create([
            'room_id' => $room->id,
            'user_id' => $user->id,
            'role' => 'admin',
            'is_banned' => false,
            'joined_at' => now(),
            'last_read_at' => now(),
        ]);
        Member::create([
            'room_id' => $room->id,
            'user_id' => $other->id,
            'role' => 'member',
            'is_banned' => false,
            'joined_at' => now(),
            'last_read_at' => now(),
        ]);

        $response = $this->actingAs($user)->getJson("/api/rooms/{$room->slug}/members");

        $response->assertStatus(200);
        $response->assertJsonCount(2);
    }

    public function test_banned_members_are_excluded_from_list(): void
    {
        $user = User::factory()->create();
        $banned = User::factory()->create();
        $room = Room::factory()->create(['type' => 'public']);

        Member::create([
            'room_id' => $room->id,
            'user_id' => $user->id,
            'role' => 'admin',
            'is_banned' => false,
            'joined_at' => now(),
            'last_read_at' => now(),
        ]);
        Member::create([
            'room_id' => $room->id,
            'user_id' => $banned->id,
            'role' => 'member',
            'is_banned' => true,
            'joined_at' => now(),
            'last_read_at' => now(),
        ]);

        $response = $this->actingAs($user)->getJson("/api/rooms/{$room->slug}/members");

        $response->assertStatus(200);
        $response->assertJsonCount(1);
    }

    public function test_non_muted_member_has_null_muted_until(): void
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

        $response = $this->actingAs($user)->getJson("/api/rooms/{$room->slug}/members");

        $response->assertStatus(200);
        $response->assertJsonPath('0.mutedUntil', null);
    }

    public function test_muted_member_has_future_muted_until(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->create(['type' => 'public']);

        $member = Member::create([
            'room_id' => $room->id,
            'user_id' => $user->id,
            'role' => 'member',
            'is_banned' => false,
            'muted_until' => now()->addMinutes(15),
            'joined_at' => now(),
            'last_read_at' => now(),
        ]);

        $response = $this->actingAs($user)->getJson("/api/rooms/{$room->slug}/members");

        $response->assertStatus(200);
        $mutedUntil = $response->json('0.mutedUntil');
        $this->assertNotNull($mutedUntil);
        $this->assertTrue(now()->parse($mutedUntil)->isFuture());
    }

    public function test_expired_mute_still_reported_but_in_the_past(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->create(['type' => 'public']);

        Member::create([
            'room_id' => $room->id,
            'user_id' => $user->id,
            'role' => 'member',
            'is_banned' => false,
            // Mute that already ended a minute ago — the API still reports
            // the raw timestamp; it's the frontend's job to treat a past
            // timestamp as "not muted" rather than the backend clearing it.
            'muted_until' => now()->subMinute(),
            'joined_at' => now(),
            'last_read_at' => now(),
        ]);

        $response = $this->actingAs($user)->getJson("/api/rooms/{$room->slug}/members");

        $response->assertStatus(200);
        $mutedUntil = $response->json('0.mutedUntil');
        $this->assertNotNull($mutedUntil);
        $this->assertTrue(now()->parse($mutedUntil)->isPast());
    }
}
