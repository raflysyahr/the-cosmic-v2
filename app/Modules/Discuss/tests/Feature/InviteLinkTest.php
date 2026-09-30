<?php

namespace App\Modules\Discuss\tests\Feature;

use App\Modules\Auth\Models\User;
use App\Modules\Discuss\Models\Member;
use App\Modules\Discuss\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InviteLinkTest extends TestCase
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

    public function test_moderator_can_generate_invite_link(): void
    {
        $moderator = User::factory()->create();
        $room = Room::factory()->create(['type' => 'invite_only']);
        $this->addMember($room, $moderator, 'moderator');

        $response = $this->actingAs($moderator)->postJson("/api/rooms/{$room->slug}/invite");

        $response->assertStatus(200);
        $response->assertJsonStructure(['invite_token', 'invite_url']);
        $this->assertNotNull($room->fresh()->settings['invite_link'] ?? null);
    }

    public function test_regular_member_cannot_generate_invite_link(): void
    {
        $regular = User::factory()->create();
        $room = Room::factory()->create(['type' => 'invite_only']);
        $this->addMember($room, $regular);

        $response = $this->actingAs($regular)->postJson("/api/rooms/{$room->slug}/invite");

        $response->assertStatus(422);
    }

    public function test_visiting_valid_invite_link_joins_and_redirects_to_room(): void
    {
        $moderator = User::factory()->create();
        $newcomer = User::factory()->create();
        $room = Room::factory()->create(['type' => 'invite_only']);
        $this->addMember($room, $moderator, 'moderator');

        $inviteResponse = $this->actingAs($moderator)->postJson("/api/rooms/{$room->slug}/invite");
        $token = $inviteResponse->json('invite_token');

        $response = $this->actingAs($newcomer)->get("/discuss/invite/{$token}");

        $response->assertRedirect(route('discuss.room', ['slug' => $room->slug]));
        $this->assertDatabaseHas('discuss_members', [
            'room_id' => $room->id,
            'user_id' => $newcomer->id,
        ]);
    }

    public function test_invalid_invite_token_404s(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/discuss/invite/not-a-real-token');

        $response->assertStatus(404);
    }

    public function test_rotating_invite_link_invalidates_the_old_one(): void
    {
        $moderator = User::factory()->create();
        $newcomer = User::factory()->create();
        $room = Room::factory()->create(['type' => 'invite_only']);
        $this->addMember($room, $moderator, 'moderator');

        $first = $this->actingAs($moderator)->postJson("/api/rooms/{$room->slug}/invite");
        $oldToken = $first->json('invite_token');

        // Rotate.
        $this->actingAs($moderator)->postJson("/api/rooms/{$room->slug}/invite");

        $response = $this->actingAs($newcomer)->get("/discuss/invite/{$oldToken}");

        $response->assertStatus(404);
    }

    public function test_banned_member_cannot_rejoin_via_invite_link(): void
    {
        $moderator = User::factory()->create();
        $banned = User::factory()->create();
        $room = Room::factory()->create(['type' => 'invite_only']);
        $this->addMember($room, $moderator, 'moderator');

        Member::create([
            'room_id' => $room->id,
            'user_id' => $banned->id,
            'role' => 'member',
            'is_banned' => true,
            'joined_at' => now(),
            'last_read_at' => now(),
        ]);

        $inviteResponse = $this->actingAs($moderator)->postJson("/api/rooms/{$room->slug}/invite");
        $token = $inviteResponse->json('invite_token');

        $response = $this->actingAs($banned)->get("/discuss/invite/{$token}");

        $response->assertStatus(403);
    }

    public function test_public_room_join_endpoint_still_works_without_a_token(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->create(['type' => 'public']);

        $response = $this->actingAs($user)->postJson("/api/rooms/{$room->slug}/join");

        $response->assertStatus(201);
    }
}
