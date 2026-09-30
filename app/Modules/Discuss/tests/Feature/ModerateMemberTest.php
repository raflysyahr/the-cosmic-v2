<?php

namespace App\Modules\Discuss\tests\Feature;

use App\Modules\Auth\Models\User;
use App\Modules\Discuss\Models\Member;
use App\Modules\Discuss\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModerateMemberTest extends TestCase
{
    use RefreshDatabase;

    private function addMember(Room $room, User $user, string $role = 'member'): Member
    {
        return Member::create([
            'room_id' => $room->id,
            'user_id' => $user->id,
            'role' => $role,
            'xp_points' => 0,
            'is_banned' => false,
            'joined_at' => now(),
            'last_read_at' => now(),
        ]);
    }

    public function test_moderator_can_kick_member_via_http(): void
    {
        $moderator = User::factory()->create();
        $target = User::factory()->create();
        $room = Room::factory()->create();
        $this->addMember($room, $moderator, 'moderator');
        $this->addMember($room, $target);

        $response = $this->actingAs($moderator)
            ->postJson("/api/rooms/{$room->slug}/members/{$target->id}/kick");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('discuss_members', [
            'room_id' => $room->id,
            'user_id' => $target->id,
        ]);
    }

    public function test_moderator_can_mute_member_via_http(): void
    {
        $moderator = User::factory()->create();
        $target = User::factory()->create();
        $room = Room::factory()->create();
        $this->addMember($room, $moderator, 'moderator');
        $targetMember = $this->addMember($room, $target);

        $response = $this->actingAs($moderator)
            ->postJson("/api/rooms/{$room->slug}/members/{$target->id}/mute", ['minutes' => 30]);

        $response->assertStatus(200);
        $this->assertNotNull($targetMember->fresh()->muted_until);
    }

    public function test_mute_requires_minutes(): void
    {
        $moderator = User::factory()->create();
        $target = User::factory()->create();
        $room = Room::factory()->create();
        $this->addMember($room, $moderator, 'moderator');
        $this->addMember($room, $target);

        $response = $this->actingAs($moderator)
            ->postJson("/api/rooms/{$room->slug}/members/{$target->id}/mute");

        $response->assertStatus(422);
    }

    public function test_moderator_can_ban_member_via_http(): void
    {
        $moderator = User::factory()->create();
        $target = User::factory()->create();
        $room = Room::factory()->create();
        $this->addMember($room, $moderator, 'moderator');
        $targetMember = $this->addMember($room, $target);

        $response = $this->actingAs($moderator)
            ->postJson("/api/rooms/{$room->slug}/members/{$target->id}/ban");

        $response->assertStatus(200);
        $this->assertTrue($targetMember->fresh()->is_banned);
    }

    public function test_regular_member_cannot_kick_via_http(): void
    {
        $regular = User::factory()->create();
        $target = User::factory()->create();
        $room = Room::factory()->create();
        $this->addMember($room, $regular);
        $this->addMember($room, $target);

        $response = $this->actingAs($regular)
            ->postJson("/api/rooms/{$room->slug}/members/{$target->id}/kick");

        $response->assertStatus(422);
        $this->assertDatabaseHas('discuss_members', [
            'room_id' => $room->id,
            'user_id' => $target->id,
        ]);
    }

    public function test_moderating_unknown_user_id_404s(): void
    {
        $moderator = User::factory()->create();
        $room = Room::factory()->create();
        $this->addMember($room, $moderator, 'moderator');

        $response = $this->actingAs($moderator)
            ->postJson("/api/rooms/{$room->slug}/members/01ARZ3NDEKTSV4RRFFQ69G5FAV/kick");

        $response->assertStatus(404);
    }
}
