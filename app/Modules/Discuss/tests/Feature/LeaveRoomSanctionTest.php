<?php

namespace App\Modules\Discuss\tests\Feature;

use App\Modules\Auth\Models\User;
use App\Modules\Discuss\Models\Member;
use App\Modules\Discuss\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeaveRoomSanctionTest extends TestCase
{
    use RefreshDatabase;

    private function addMember(Room $room, User $user, string $role = 'member', array $extra = []): Member
    {
        return Member::create(array_merge([
            'room_id' => $room->id,
            'user_id' => $user->id,
            'role' => $role,
            'is_banned' => false,
            'joined_at' => now(),
            'last_read_at' => now(),
        ], $extra));
    }

    public function test_banned_member_cannot_leave_and_stays_banned(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->create(['type' => 'public']);
        $this->addMember($room, $user, extra: ['is_banned' => true]);

        $this->actingAs($user)->deleteJson("/api/rooms/{$room->slug}/leave")->assertUnprocessable();

        $this->assertDatabaseHas('discuss_members', [
            'room_id' => $room->id,
            'user_id' => $user->id,
            'is_banned' => true,
        ]);
    }

    public function test_banned_member_cannot_rejoin_public_room_after_attempting_to_leave(): void
    {
        $moderator = User::factory()->create();
        $user = User::factory()->create();
        $room = Room::factory()->create(['type' => 'public']);
        $this->addMember($room, $moderator, 'moderator');
        $this->addMember($room, $user);

        $this->actingAs($moderator)->postJson("/api/rooms/{$room->slug}/members/{$user->id}/ban")->assertOk();

        $this->actingAs($user)->deleteJson("/api/rooms/{$room->slug}/leave")->assertUnprocessable();
        $this->actingAs($user)->postJson("/api/rooms/{$room->slug}/join")->assertUnprocessable();

        $this->assertTrue((bool) Member::where('room_id', $room->id)->where('user_id', $user->id)->value('is_banned'));
    }

    public function test_muted_member_cannot_leave_to_clear_the_mute(): void
    {
        $moderator = User::factory()->create();
        $user = User::factory()->create();
        $room = Room::factory()->create(['type' => 'public']);
        $this->addMember($room, $moderator, 'moderator');
        $this->addMember($room, $user);

        $this->actingAs($moderator)
            ->postJson("/api/rooms/{$room->slug}/members/{$user->id}/mute", ['minutes' => 30])
            ->assertOk();

        $this->actingAs($user)->deleteJson("/api/rooms/{$room->slug}/leave")->assertUnprocessable();

        $member = Member::where('room_id', $room->id)->where('user_id', $user->id)->first();
        $this->assertNotNull($member);
        $this->assertTrue($member->muted_until->isFuture());
    }

    public function test_member_can_leave_after_mute_expires(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->create(['type' => 'public']);
        $this->addMember($room, $user, extra: ['muted_until' => now()->subMinute()]);

        $this->actingAs($user)->deleteJson("/api/rooms/{$room->slug}/leave")->assertOk();

        $this->assertDatabaseMissing('discuss_members', ['room_id' => $room->id, 'user_id' => $user->id]);
    }

    public function test_regular_member_can_still_leave_and_rejoin(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->create(['type' => 'public']);
        $this->addMember($room, $user);

        $this->actingAs($user)->deleteJson("/api/rooms/{$room->slug}/leave")->assertOk();
        $this->assertDatabaseMissing('discuss_members', ['room_id' => $room->id, 'user_id' => $user->id]);

        $this->actingAs($user)->postJson("/api/rooms/{$room->slug}/join")->assertSuccessful();
        $this->assertDatabaseHas('discuss_members', ['room_id' => $room->id, 'user_id' => $user->id]);
    }
}
