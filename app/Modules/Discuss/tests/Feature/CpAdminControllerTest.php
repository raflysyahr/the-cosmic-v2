<?php

namespace App\Modules\Discuss\tests\Feature;

use App\Modules\Auth\Models\User;
use App\Modules\Discuss\Data\SendMessageData;
use App\Modules\Discuss\Models\CpEvent;
use App\Modules\Discuss\Models\CpLog;
use App\Modules\Discuss\Models\Member;
use App\Modules\Discuss\Models\Room;
use App\Modules\Discuss\Services\MessageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CpAdminControllerTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function validEvent(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Weekend Boost',
            'multiplier' => 2,
            'sources' => ['message', 'reply'],
            'starts_at' => now()->subHour()->toIso8601String(),
            'ends_at' => now()->addDay()->toIso8601String(),
        ], $overrides);
    }

    public function test_non_admin_cannot_read_or_change_settings(): void
    {
        $reader = User::factory()->create();

        $this->actingAs($reader)->getJson('/api/admin/cp/settings')->assertStatus(422);
        $this->actingAs($reader)->putJson('/api/admin/cp/settings', ['message_points' => 99])->assertStatus(422);

        $this->assertDatabaseCount('discuss_cp_settings', 0);
    }

    public function test_admin_can_read_effective_settings_and_defaults(): void
    {
        $this->actingAs($this->admin())
            ->getJson('/api/admin/cp/settings')
            ->assertOk()
            ->assertJsonPath('settings.message_points', 1)
            ->assertJsonPath('settings.reply_tier1_points', 4)
            ->assertJsonPath('settings.enabled', true)
            ->assertJsonPath('defaults.reaction_points', 2);
    }

    public function test_admin_update_persists_and_changes_real_payouts(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->putJson('/api/admin/cp/settings', ['message_points' => 3])
            ->assertOk()
            ->assertJsonPath('settings.message_points', 3)
            ->assertJsonPath('defaults.message_points', 1);

        $room = Room::factory()->create();
        $member = Member::create([
            'room_id' => $room->id, 'user_id' => $admin->id, 'role' => 'member', 'xp_points' => 0,
            'is_banned' => false, 'joined_at' => now(), 'last_read_at' => now(),
        ]);
        app(MessageService::class)->send(new SendMessageData(
            roomId: $room->id, userId: $admin->id, replyToId: null, type: 'text',
            body: 'Paid at the new rate', attachments: [], metadata: [],
        ));

        $this->assertSame(3, (int) $member->fresh()->xp_points);
    }

    public function test_null_value_resets_a_setting_to_default(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->putJson('/api/admin/cp/settings', ['message_points' => 5])->assertOk();

        $this->actingAs($admin)
            ->putJson('/api/admin/cp/settings', ['message_points' => null])
            ->assertOk()
            ->assertJsonPath('settings.message_points', 1);

        $this->assertDatabaseMissing('discuss_cp_settings', ['key' => 'message_points']);
    }

    public function test_invalid_settings_are_rejected(): void
    {
        $this->actingAs($this->admin())
            ->putJson('/api/admin/cp/settings', ['message_points' => -1, 'burst_window_seconds' => 0])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['message_points', 'burst_window_seconds']);
    }

    public function test_admin_can_create_list_update_and_delete_events(): void
    {
        $admin = $this->admin();

        $id = $this->actingAs($admin)
            ->postJson('/api/admin/cp/events', $this->validEvent())
            ->assertStatus(201)
            ->assertJsonPath('event.multiplier', 2)
            ->assertJsonPath('event.is_running', true)
            ->json('event.id');

        $this->assertDatabaseHas('discuss_cp_events', ['id' => $id, 'multiplier_pct' => 200, 'created_by' => $admin->id]);

        $this->actingAs($admin)->getJson('/api/admin/cp/events')->assertOk()->assertJsonCount(1, 'events');

        $this->actingAs($admin)
            ->putJson("/api/admin/cp/events/{$id}", ['is_active' => false, 'multiplier' => 3])
            ->assertOk()
            ->assertJsonPath('event.is_running', false)
            ->assertJsonPath('event.multiplier', 3);

        $this->actingAs($admin)->deleteJson("/api/admin/cp/events/{$id}")->assertOk();
        $this->assertDatabaseMissing('discuss_cp_events', ['id' => $id]);
    }

    public function test_event_validation_rejects_bad_input(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->postJson('/api/admin/cp/events', $this->validEvent(['multiplier' => 11]))
            ->assertStatus(422)->assertJsonValidationErrors(['multiplier']);

        $this->actingAs($admin)
            ->postJson('/api/admin/cp/events', $this->validEvent(['sources' => ['revoke']]))
            ->assertStatus(422);

        $this->actingAs($admin)
            ->postJson('/api/admin/cp/events', $this->validEvent([
                'starts_at' => now()->addDay()->toIso8601String(),
                'ends_at' => now()->toIso8601String(),
            ]))
            ->assertStatus(422)->assertJsonValidationErrors(['ends_at']);

        $this->assertDatabaseCount('discuss_cp_events', 0);
    }

    public function test_partial_update_cannot_push_end_before_start(): void
    {
        $admin = $this->admin();
        $id = $this->actingAs($admin)->postJson('/api/admin/cp/events', $this->validEvent())->json('event.id');

        $this->actingAs($admin)
            ->putJson("/api/admin/cp/events/{$id}", ['ends_at' => now()->subDays(2)->toIso8601String()])
            ->assertStatus(422);
    }

    public function test_events_can_target_the_phase_two_sources(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->postJson('/api/admin/cp/events', $this->validEvent(['sources' => ['helpful', 'best_answer', 'daily_bonus', 'streak']]))
            ->assertStatus(201)
            ->assertJsonPath('event.sources.1', 'best_answer');
    }

    public function test_admin_can_tune_phase_two_settings(): void
    {
        $this->actingAs($this->admin())
            ->putJson('/api/admin/cp/settings', ['helpful_points' => 30, 'streak_7_points' => 40, 'daily_bonus_points' => 0])
            ->assertOk()
            ->assertJsonPath('settings.helpful_points', 30)
            ->assertJsonPath('settings.streak_7_points', 40)
            ->assertJsonPath('settings.daily_bonus_points', 0)
            ->assertJsonPath('defaults.helpful_points', 15)
            ->assertJsonPath('defaults.best_answer_points', 25);
    }

    public function test_non_admin_cannot_manage_events(): void
    {
        $reader = User::factory()->create();
        $event = CpEvent::create([
            'name' => 'Existing', 'multiplier_pct' => 200,
            'starts_at' => now()->subHour(), 'ends_at' => now()->addHour(), 'is_active' => true,
        ]);

        $this->actingAs($reader)->postJson('/api/admin/cp/events', $this->validEvent())->assertStatus(422);
        $this->actingAs($reader)->getJson('/api/admin/cp/events')->assertStatus(422);
        $this->actingAs($reader)->putJson("/api/admin/cp/events/{$event->id}", ['is_active' => false])->assertStatus(422);
        $this->actingAs($reader)->deleteJson("/api/admin/cp/events/{$event->id}")->assertStatus(422);

        $this->assertDatabaseCount('discuss_cp_events', 1);
        $this->assertTrue($event->fresh()->is_active);
    }

    public function test_any_logged_in_user_sees_only_running_events(): void
    {
        CpEvent::create(['name' => 'Running', 'multiplier_pct' => 200, 'starts_at' => now()->subHour(), 'ends_at' => now()->addHour(), 'is_active' => true]);
        CpEvent::create(['name' => 'Upcoming', 'multiplier_pct' => 200, 'starts_at' => now()->addDay(), 'ends_at' => now()->addDays(2), 'is_active' => true]);
        CpEvent::create(['name' => 'Switched off', 'multiplier_pct' => 200, 'starts_at' => now()->subHour(), 'ends_at' => now()->addHour(), 'is_active' => false]);

        $this->actingAs(User::factory()->create())
            ->getJson('/api/cp/active-events')
            ->assertOk()
            ->assertJsonCount(1, 'events')
            ->assertJsonPath('events.0.name', 'Running');
    }
}
