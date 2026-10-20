<?php

namespace App\Modules\Discuss\tests\Feature;

use App\Modules\Auth\Models\User;
use App\Modules\Discuss\Models\Member;
use App\Modules\Discuss\Models\Message;
use App\Modules\Discuss\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BannedMemberActionsTest extends TestCase
{
    use RefreshDatabase;

    private function addMember(Room $room, User $user, bool $banned = false, string $role = 'member'): void
    {
        Member::create([
            'room_id' => $room->id,
            'user_id' => $user->id,
            'role' => $role,
            'is_banned' => $banned,
            'joined_at' => now(),
            'last_read_at' => now(),
        ]);
    }

    public function test_banned_member_cannot_delete_their_own_message(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->create(['type' => 'public']);
        $this->addMember($room, $user, banned: true);
        $message = Message::factory()->create(['room_id' => $room->id, 'user_id' => $user->id]);

        $this->actingAs($user)
            ->deleteJson("/api/rooms/{$room->slug}/messages/{$message->id}")
            ->assertUnprocessable();

        $this->assertFalse((bool) $message->fresh()->is_deleted);
    }

    public function test_active_member_can_still_delete_their_own_message(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->create(['type' => 'public']);
        $this->addMember($room, $user);
        $message = Message::factory()->create(['room_id' => $room->id, 'user_id' => $user->id]);

        $this->actingAs($user)
            ->deleteJson("/api/rooms/{$room->slug}/messages/{$message->id}")
            ->assertOk();

        $this->assertTrue((bool) $message->fresh()->is_deleted);
    }

    public function test_banned_moderator_cannot_delete_others_message(): void
    {
        $mod = User::factory()->create();
        $author = User::factory()->create();
        $room = Room::factory()->create(['type' => 'public']);
        $this->addMember($room, $mod, banned: true, role: 'moderator');
        $message = Message::factory()->create(['room_id' => $room->id, 'user_id' => $author->id]);

        $this->actingAs($mod)
            ->deleteJson("/api/rooms/{$room->slug}/messages/{$message->id}")
            ->assertUnprocessable();

        $this->assertFalse((bool) $message->fresh()->is_deleted);
    }

    public function test_banned_member_cannot_react(): void
    {
        $banned = User::factory()->create();
        $room = Room::factory()->create(['type' => 'public']);
        $this->addMember($room, $banned, banned: true);
        $message = Message::factory()->create(['room_id' => $room->id]);

        $this->actingAs($banned)
            ->postJson("/api/rooms/{$room->slug}/messages/{$message->id}/reactions", ['emote_id' => 'emote-1'])
            ->assertUnprocessable();

        $this->assertDatabaseCount('discuss_reactions', 0);
    }

    public function test_room_page_reports_banned_state_to_the_client(): void
    {
        $banned = User::factory()->create();
        $normal = User::factory()->create();
        $room = Room::factory()->create(['type' => 'public']);
        $this->addMember($room, $banned, banned: true);
        $this->addMember($room, $normal);

        $this->actingAs($banned)->get("/discuss/{$room->slug}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('isBanned', true));

        $this->actingAs($normal)->get("/discuss/{$room->slug}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('isBanned', false));
    }
}
