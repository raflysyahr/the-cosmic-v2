<?php

namespace App\Modules\Discuss\tests\Feature;

use App\Modules\Auth\Models\User;
use App\Modules\Discuss\Models\CpLog;
use App\Modules\Discuss\Models\Member;
use App\Modules\Discuss\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeaderboardControllerTest extends TestCase
{
    use RefreshDatabase;

    private function log(User $user, Room $room, int $amount, $createdAt = null, string $source = 'message'): void
    {
        CpLog::unguarded(fn () => CpLog::create([
            'user_id' => $user->id,
            'room_id' => $room->id,
            'source' => $source,
            'reference' => uniqid('ref-', true),
            'base_amount' => max($amount, 0),
            'multiplier_pct' => 100,
            'amount' => $amount,
            'created_at' => $createdAt ?? now(),
        ]));
    }

    private function join(Room $room, User $user): void
    {
        Member::create([
            'room_id' => $room->id, 'user_id' => $user->id, 'role' => 'member', 'xp_points' => 0,
            'is_banned' => false, 'joined_at' => now(), 'last_read_at' => now(),
        ]);
    }

    public function test_weekly_board_excludes_old_points_but_all_time_includes_them(): void
    {
        $room = Room::factory()->create();
        [$alice, $bob, $carol] = [User::factory()->create(), User::factory()->create(), User::factory()->create()];

        $this->log($alice, $room, 50);
        $this->log($bob, $room, 30);
        $this->log($carol, $room, 100, now()->startOfWeek()->subDay());

        $weekly = $this->actingAs($alice)->getJson('/api/discuss/leaderboard?period=weekly')->assertOk();
        $weekly->assertJsonCount(2, 'entries')
            ->assertJsonPath('entries.0.userId', $alice->id)
            ->assertJsonPath('entries.0.rank', 1)
            ->assertJsonPath('entries.0.points', 50)
            ->assertJsonPath('entries.1.userId', $bob->id)
            ->assertJsonPath('me', 50);

        $this->actingAs($alice)->getJson('/api/discuss/leaderboard?period=all')
            ->assertOk()
            ->assertJsonCount(3, 'entries')
            ->assertJsonPath('entries.0.userId', $carol->id);
    }

    public function test_revoked_points_drop_user_off_the_board(): void
    {
        $room = Room::factory()->create();
        $alice = User::factory()->create();
        $this->log($alice, $room, 10);
        $this->log($alice, $room, -10, null, 'revoke');

        $this->actingAs($alice)->getJson('/api/discuss/leaderboard')
            ->assertOk()
            ->assertJsonCount(0, 'entries')
            ->assertJsonPath('me', 0);
    }

    public function test_unknown_period_falls_back_to_weekly(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson('/api/discuss/leaderboard?period=forever')
            ->assertOk()
            ->assertJsonPath('period', 'weekly');
    }

    public function test_global_board_leaves_out_points_from_non_public_rooms(): void
    {
        $public = Room::factory()->create();
        $private = Room::factory()->create(['type' => 'private']);
        [$alice, $bob] = [User::factory()->create(), User::factory()->create()];

        $this->log($alice, $public, 5);
        $this->log($bob, $private, 500);

        $this->actingAs($alice)->getJson('/api/discuss/leaderboard')
            ->assertOk()
            ->assertJsonCount(1, 'entries')
            ->assertJsonPath('entries.0.userId', $alice->id);
    }

    public function test_room_board_is_scoped_to_that_room(): void
    {
        $roomA = Room::factory()->create();
        $roomB = Room::factory()->create();
        [$alice, $bob] = [User::factory()->create(), User::factory()->create()];
        $this->log($alice, $roomA, 7);
        $this->log($bob, $roomB, 9);

        $this->actingAs($alice)->getJson("/api/rooms/{$roomA->slug}/leaderboard")
            ->assertOk()
            ->assertJsonCount(1, 'entries')
            ->assertJsonPath('entries.0.userId', $alice->id);
    }

    public function test_private_room_board_is_members_only(): void
    {
        $room = Room::factory()->create(['type' => 'private']);
        [$member, $stranger] = [User::factory()->create(), User::factory()->create()];
        $this->join($room, $member);
        $this->log($member, $room, 12);

        $this->actingAs($member)->getJson("/api/rooms/{$room->slug}/leaderboard")->assertOk();
        $this->actingAs($stranger)->getJson("/api/rooms/{$room->slug}/leaderboard")->assertStatus(403);
    }
}
