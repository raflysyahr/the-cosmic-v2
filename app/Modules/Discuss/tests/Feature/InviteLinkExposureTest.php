<?php

namespace App\Modules\Discuss\tests\Feature;

use App\Modules\Auth\Models\User;
use App\Modules\Discuss\Models\Member;
use App\Modules\Discuss\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InviteLinkExposureTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'TOKEN-RAHASIA-1234';

    private function inviteRoom(array $attrs = []): Room
    {
        return Room::factory()->create(array_merge([
            'type' => 'invite_only',
            'settings' => [
                'slow_mode_seconds' => 0,
                'max_members' => 500,
                'xp_per_message' => 5,
                'invite_link' => self::TOKEN,
            ],
        ], $attrs));
    }

    private function addMember(Room $room, User $user, string $role = 'member'): void
    {
        Member::create([
            'room_id' => $room->id,
            'user_id' => $user->id,
            'role' => $role,
            'is_banned' => false,
            'joined_at' => now(),
            'last_read_at' => now(),
        ]);
    }

    public function test_room_api_does_not_expose_invite_token_to_members(): void
    {
        $member = User::factory()->create();
        $room = $this->inviteRoom();
        $this->addMember($room, $member);

        $response = $this->actingAs($member)->getJson("/api/rooms/{$room->slug}")->assertOk();

        $this->assertArrayNotHasKey('invite_link', $response->json('data.settings'));
        $this->assertSame(500, $response->json('data.settings.max_members'));
        $this->assertStringNotContainsString(self::TOKEN, $response->getContent());
    }

    public function test_room_api_does_not_expose_invite_token_even_to_managers(): void
    {
        $admin = User::factory()->create();
        $room = $this->inviteRoom(['owner_user_id' => $admin->id]);
        $this->addMember($room, $admin, 'admin');

        $response = $this->actingAs($admin)->getJson("/api/rooms/{$room->slug}")->assertOk();

        $this->assertStringNotContainsString(self::TOKEN, $response->getContent());
    }

    public function test_room_update_response_does_not_expose_invite_token(): void
    {
        $admin = User::factory()->create();
        $room = $this->inviteRoom(['owner_user_id' => $admin->id]);
        $this->addMember($room, $admin, 'admin');

        $response = $this->actingAs($admin)
            ->putJson("/api/rooms/{$room->slug}", ['name' => 'Nama Baru'])
            ->assertOk();

        $this->assertStringNotContainsString(self::TOKEN, $response->getContent());
        // Token di database tetap utuh.
        $this->assertSame(self::TOKEN, $room->fresh()->settings['invite_link']);
    }

    public function test_room_page_props_do_not_contain_invite_token(): void
    {
        $member = User::factory()->create();
        $room = $this->inviteRoom();
        $this->addMember($room, $member);

        $page = $this->actingAs($member)->get("/discuss/{$room->slug}")->assertOk();

        $this->assertStringNotContainsString(self::TOKEN, $page->getContent());
    }

    public function test_about_page_props_do_not_contain_invite_token(): void
    {
        $member = User::factory()->create();
        $room = $this->inviteRoom();
        $this->addMember($room, $member);

        $page = $this->actingAs($member)->get("/discuss/{$room->slug}/about")->assertOk();

        $this->assertStringNotContainsString(self::TOKEN, $page->getContent());
    }

    public function test_public_room_page_does_not_leak_a_leftover_invite_token(): void
    {
        $viewer = User::factory()->create();
        $room = $this->inviteRoom(['type' => 'public']);

        $page = $this->actingAs($viewer)->get("/discuss/{$room->slug}")->assertOk();

        $this->assertStringNotContainsString(self::TOKEN, $page->getContent());
    }

    public function test_model_serialization_strips_the_token_without_mutating_it(): void
    {
        $room = $this->inviteRoom();

        $this->assertArrayNotHasKey('invite_link', $room->toArray()['settings']);
        $this->assertStringNotContainsString(self::TOKEN, $room->toJson());
        $this->assertArrayNotHasKey('invite_link', $room->clientSettings());

        // Kode server tetap bisa membaca token untuk validasi join.
        $this->assertSame(self::TOKEN, $room->settings['invite_link']);
    }

    public function test_manager_still_receives_token_when_generating_and_it_works_for_joining(): void
    {
        $moderator = User::factory()->create();
        $joiner = User::factory()->create();
        $room = $this->inviteRoom(['settings' => ['slow_mode_seconds' => 0, 'max_members' => 500, 'xp_per_message' => 5]]);
        $this->addMember($room, $moderator, 'moderator');

        $token = $this->actingAs($moderator)->postJson("/api/rooms/{$room->slug}/invite")
            ->assertOk()
            ->json('invite_token');

        $this->assertNotEmpty($token);

        $this->actingAs($joiner)->postJson("/api/rooms/{$room->slug}/join", ['invite_token' => $token])
            ->assertSuccessful();
        $this->assertDatabaseHas('discuss_members', ['room_id' => $room->id, 'user_id' => $joiner->id]);
    }
}
