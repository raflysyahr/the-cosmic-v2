<?php

namespace App\Modules\Discuss\tests\Feature;

use App\Modules\Auth\Models\User;
use App\Modules\Discuss\Models\Member;
use App\Modules\Discuss\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoomAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_view_private_room(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->create(['type' => 'private']);

        Member::create([
            'room_id' => $room->id,
            'user_id' => $user->id,
            'role' => 'member',
            'is_banned' => false,
            'joined_at' => now(),
            'last_read_at' => now(),
        ]);

        $response = $this->actingAs($user)->get("/discuss/{$room->slug}");

        $response->assertStatus(200);
    }

    public function test_non_member_cannot_view_private_room(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->create(['type' => 'private']);

        $response = $this->actingAs($user)->get("/discuss/{$room->slug}");

        $response->assertStatus(403);
    }

    public function test_stranger_cannot_view_someone_elses_direct_chat(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $stranger = User::factory()->create();

        $room = app(\App\Modules\Discuss\Services\RoomService::class)
            ->findOrCreateDirectChat($user1->id, $user2->id);

        $response = $this->actingAs($stranger)->get("/discuss/{$room->slug}");

        $response->assertStatus(403);
    }

    public function test_participant_can_view_their_direct_chat(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $room = app(\App\Modules\Discuss\Services\RoomService::class)
            ->findOrCreateDirectChat($user1->id, $user2->id);

        $response = $this->actingAs($user2)->get("/discuss/{$room->slug}");

        $response->assertStatus(200);
    }

    public function test_any_authenticated_user_can_view_public_room(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->create(['type' => 'public']);

        $response = $this->actingAs($user)->get("/discuss/{$room->slug}");

        $response->assertStatus(200);
    }
}
