<?php

namespace App\Modules\Discuss\tests\Feature;

use App\Modules\Auth\Models\User;
use App\Modules\Discuss\Models\Member;
use App\Modules\Discuss\Models\Rank;
use App\Modules\Discuss\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RankControllerTest extends TestCase
{
    use RefreshDatabase;

    private function addMember(Room $room, User $user, string $role = 'member'): Member
    {
        return Member::create([
            'room_id' => $room->id,
            'user_id' => $user->id,
            'role' => $role,
            'is_banned' => false,
            'joined_at' => now(),
            'last_read_at' => now(),
        ]);
    }

    public function test_can_list_ranks_for_room(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->create();
        $this->addMember($room, $user);
        Rank::create(['room_id' => $room->id, 'name' => 'Newcomer', 'label_color' => '#666', 'min_xp' => 0, 'order' => 1]);

        $response = $this->actingAs($user)->getJson("/api/rooms/{$room->slug}/ranks");

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'ranks');
    }

    public function test_moderator_can_create_rank(): void
    {
        $moderator = User::factory()->create();
        $room = Room::factory()->create();
        $this->addMember($room, $moderator, 'moderator');

        $response = $this->actingAs($moderator)->postJson("/api/rooms/{$room->slug}/ranks", [
            'name' => 'Veteran',
            'label_color' => '#00ff00',
            'min_xp' => 500,
            'order' => 3,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('discuss_ranks', ['name' => 'Veteran', 'room_id' => $room->id]);
    }

    public function test_regular_member_cannot_create_rank(): void
    {
        $regular = User::factory()->create();
        $room = Room::factory()->create();
        $this->addMember($room, $regular);

        $response = $this->actingAs($regular)->postJson("/api/rooms/{$room->slug}/ranks", [
            'name' => 'Veteran',
            'label_color' => '#00ff00',
            'min_xp' => 500,
            'order' => 3,
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('discuss_ranks', ['name' => 'Veteran']);
    }

    public function test_non_member_cannot_create_rank(): void
    {
        $outsider = User::factory()->create();
        $room = Room::factory()->create();

        $response = $this->actingAs($outsider)->postJson("/api/rooms/{$room->slug}/ranks", [
            'name' => 'Veteran',
            'label_color' => '#00ff00',
            'min_xp' => 500,
            'order' => 3,
        ]);

        $response->assertStatus(422);
    }

    public function test_moderator_can_update_rank(): void
    {
        $moderator = User::factory()->create();
        $room = Room::factory()->create();
        $this->addMember($room, $moderator, 'moderator');
        $rank = Rank::create(['room_id' => $room->id, 'name' => 'Newcomer', 'label_color' => '#666', 'min_xp' => 0, 'order' => 1]);

        $response = $this->actingAs($moderator)->putJson("/api/rooms/{$room->slug}/ranks/{$rank->id}", [
            'name' => 'Rookie',
        ]);

        $response->assertStatus(200);
        $this->assertEquals('Rookie', $rank->fresh()->name);
    }

    public function test_regular_member_cannot_update_rank(): void
    {
        $regular = User::factory()->create();
        $room = Room::factory()->create();
        $this->addMember($room, $regular);
        $rank = Rank::create(['room_id' => $room->id, 'name' => 'Newcomer', 'label_color' => '#666', 'min_xp' => 0, 'order' => 1]);

        $response = $this->actingAs($regular)->putJson("/api/rooms/{$room->slug}/ranks/{$rank->id}", [
            'name' => 'Rookie',
        ]);

        $response->assertStatus(422);
        $this->assertEquals('Newcomer', $rank->fresh()->name);
    }

    public function test_cannot_update_rank_belonging_to_a_different_room(): void
    {
        $moderator = User::factory()->create();
        $roomA = Room::factory()->create();
        $roomB = Room::factory()->create();
        $this->addMember($roomA, $moderator, 'moderator');
        $this->addMember($roomB, $moderator, 'moderator');
        $rankInRoomB = Rank::create(['room_id' => $roomB->id, 'name' => 'Newcomer', 'label_color' => '#666', 'min_xp' => 0, 'order' => 1]);

        // Moderator of roomA tries to update a rank that actually belongs to roomB,
        // using roomA's slug in the URL.
        $response = $this->actingAs($moderator)->putJson("/api/rooms/{$roomA->slug}/ranks/{$rankInRoomB->id}", [
            'name' => 'Hacked',
        ]);

        $response->assertStatus(404);
        $this->assertEquals('Newcomer', $rankInRoomB->fresh()->name);
    }

    public function test_moderator_can_delete_rank(): void
    {
        $moderator = User::factory()->create();
        $room = Room::factory()->create();
        $this->addMember($room, $moderator, 'moderator');
        $rank = Rank::create(['room_id' => $room->id, 'name' => 'Newcomer', 'label_color' => '#666', 'min_xp' => 0, 'order' => 1]);

        $response = $this->actingAs($moderator)->deleteJson("/api/rooms/{$room->slug}/ranks/{$rank->id}");

        $response->assertStatus(200);
        $this->assertNull(Rank::find($rank->id));
    }

    public function test_regular_member_cannot_delete_rank(): void
    {
        $regular = User::factory()->create();
        $room = Room::factory()->create();
        $this->addMember($room, $regular);
        $rank = Rank::create(['room_id' => $room->id, 'name' => 'Newcomer', 'label_color' => '#666', 'min_xp' => 0, 'order' => 1]);

        $response = $this->actingAs($regular)->deleteJson("/api/rooms/{$room->slug}/ranks/{$rank->id}");

        $response->assertStatus(422);
        $this->assertNotNull(Rank::find($rank->id));
    }
}
