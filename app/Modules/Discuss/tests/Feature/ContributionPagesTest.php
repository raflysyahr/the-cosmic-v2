<?php

namespace App\Modules\Discuss\tests\Feature;

use App\Modules\Auth\Models\User;
use App\Modules\Discuss\Models\Member;
use App\Modules\Discuss\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContributionPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_leaderboard_page_renders_for_any_logged_in_user(): void
    {
        $this->actingAs(User::factory()->create())->get('/discuss/leaderboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Discuss/Leaderboard')->where('isAdmin', false));

        $this->actingAs(User::factory()->admin()->create())->get('/discuss/leaderboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('isAdmin', true));
    }

    public function test_admin_page_is_admin_only(): void
    {
        $this->actingAs(User::factory()->create())->get('/discuss/leaderboard/admin')->assertStatus(403);

        $this->actingAs(User::factory()->admin()->create())->get('/discuss/leaderboard/admin')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Discuss/CpAdmin'));
    }

    public function test_reports_page_is_for_room_moderators_only(): void
    {
        $room = Room::factory()->create();
        $member = User::factory()->create();
        $moderator = User::factory()->create();
        foreach ([[$member, 'member'], [$moderator, 'moderator']] as [$user, $role]) {
            Member::create([
                'room_id' => $room->id, 'user_id' => $user->id, 'role' => $role, 'xp_points' => 0,
                'is_banned' => false, 'joined_at' => now(), 'last_read_at' => now(),
            ]);
        }

        $this->actingAs($member)->get("/discuss/{$room->slug}/reports")->assertStatus(403);

        $this->actingAs($moderator)->get("/discuss/{$room->slug}/reports")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Discuss/Reports')->where('room.slug', $room->slug));

        $this->actingAs($moderator)->get('/discuss/no-such-room/reports')->assertStatus(404);
    }

    public function test_achievements_endpoint_lists_progress_for_the_current_user(): void
    {
        $this->actingAs(User::factory()->create())->getJson('/api/discuss/achievements')
            ->assertOk()
            ->assertJsonCount(6, 'achievements')
            ->assertJsonPath('achievements.0.key', 'first_reply')
            ->assertJsonPath('achievements.0.unlocked', false);
    }
}
