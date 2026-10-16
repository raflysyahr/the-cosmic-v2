<?php

namespace App\Modules\Discuss\tests\Feature;

use App\Modules\Auth\Models\User;
use App\Modules\Discuss\Models\Member;
use App\Modules\Discuss\Models\Room;
use App\Modules\Discuss\Services\RoomService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoomEditAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function addMember(Room $room, User $user, string $role = 'member', bool $banned = false): Member
    {
        return Member::create([
            'room_id' => $room->id,
            'user_id' => $user->id,
            'role' => $role,
            'is_banned' => $banned,
            'joined_at' => now(),
            'last_read_at' => now(),
        ]);
    }

    // ── Edit: owner, moderator, dan admin room ──

    public function test_moderator_can_update_room(): void
    {
        $moderator = User::factory()->create();
        $room = Room::factory()->create(['name' => 'Original']);
        $this->addMember($room, $moderator, 'moderator');

        $this->actingAs($moderator)->putJson("/api/rooms/{$room->slug}", ['name' => 'By Moderator'])
            ->assertOk();

        $this->assertSame('By Moderator', $room->fresh()->name);
    }

    public function test_moderator_can_archive_room(): void
    {
        $moderator = User::factory()->create();
        $room = Room::factory()->create(['is_active' => true]);
        $this->addMember($room, $moderator, 'moderator');

        $this->actingAs($moderator)->deleteJson("/api/rooms/{$room->slug}")->assertOk();

        $this->assertFalse($room->fresh()->is_active);
    }

    public function test_regular_member_and_stranger_cannot_update_room(): void
    {
        $member = User::factory()->create();
        $stranger = User::factory()->create();
        $room = Room::factory()->create(['name' => 'Original']);
        $this->addMember($room, $member, 'member');

        $this->actingAs($member)->putJson("/api/rooms/{$room->slug}", ['name' => 'Hacked'])
            ->assertUnprocessable();
        $this->actingAs($stranger)->putJson("/api/rooms/{$room->slug}", ['name' => 'Hacked'])
            ->assertUnprocessable();

        $this->assertSame('Original', $room->fresh()->name);
    }

    public function test_banned_moderator_cannot_update_room(): void
    {
        $moderator = User::factory()->create();
        $room = Room::factory()->create(['name' => 'Before']);
        $this->addMember($room, $moderator, 'moderator', banned: true);

        $this->actingAs($moderator)->putJson("/api/rooms/{$room->slug}", ['name' => 'After'])
            ->assertUnprocessable();

        $this->assertSame('Before', $room->fresh()->name);
    }

    public function test_owner_can_update_room_even_without_admin_membership_role(): void
    {
        $owner = User::factory()->create();
        $room = Room::factory()->create(['owner_user_id' => $owner->id, 'name' => 'Before']);
        $this->addMember($room, $owner, 'member');

        $this->actingAs($owner)->putJson("/api/rooms/{$room->slug}", ['name' => 'After'])->assertOk();

        $this->assertSame('After', $room->fresh()->name);
    }

    public function test_banned_admin_cannot_update_room(): void
    {
        $admin = User::factory()->create();
        $room = Room::factory()->create(['owner_user_id' => $admin->id, 'name' => 'Before']);
        $this->addMember($room, $admin, 'admin', banned: true);

        $this->actingAs($admin)->putJson("/api/rooms/{$room->slug}", ['name' => 'After'])
            ->assertUnprocessable();

        $this->assertSame('Before', $room->fresh()->name);
    }

    public function test_update_keeps_server_managed_settings(): void
    {
        $admin = User::factory()->create();
        $room = Room::factory()->create([
            'owner_user_id' => $admin->id,
            'type' => 'invite_only',
            'settings' => ['slow_mode_seconds' => 0, 'max_members' => 500, 'xp_per_message' => 5, 'invite_link' => 'secret-token'],
        ]);
        $this->addMember($room, $admin, 'admin');

        $this->actingAs($admin)->putJson("/api/rooms/{$room->slug}", [
            'settings' => ['slow_mode_seconds' => 30],
        ])->assertOk();

        $settings = $room->fresh()->settings;
        $this->assertSame(30, $settings['slow_mode_seconds']);
        $this->assertSame('secret-token', $settings['invite_link']);
        $this->assertSame(500, $settings['max_members']);
    }

    public function test_update_rejects_unknown_settings_keys_and_server_owned_fields(): void
    {
        $admin = User::factory()->create();
        $room = Room::factory()->create(['owner_user_id' => $admin->id]);
        $this->addMember($room, $admin, 'admin');

        $this->actingAs($admin)->putJson("/api/rooms/{$room->slug}", [
            'settings' => ['invite_link' => 'my-own-token'],
        ])->assertUnprocessable()->assertJsonValidationErrors(['settings']);

        $this->actingAs($admin)->putJson("/api/rooms/{$room->slug}", [
            'context_type' => 'direct',
            'context_id' => 'someone_else',
            'owner_user_id' => $admin->id,
            'slug' => 'new-slug',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['context_type', 'context_id', 'owner_user_id', 'slug']);
    }

    // ── DM tidak boleh diubah / dibuka ──

    public function test_direct_chat_participant_cannot_change_dm_type_or_settings(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $dm = app(RoomService::class)->findOrCreateDirectChat($alice->id, $bob->id);

        $this->actingAs($alice)->putJson("/api/rooms/{$dm->slug}", ['type' => 'public'])->assertForbidden();
        $this->actingAs($alice)->putJson("/api/rooms/{$dm->slug}", ['name' => 'Renamed'])->assertForbidden();

        $fresh = $dm->fresh();
        $this->assertSame('private', $fresh->type->value);
        $this->assertSame(true, $fresh->settings['is_direct']);
    }

    public function test_direct_chat_cannot_get_an_invite_link(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $dm = app(RoomService::class)->findOrCreateDirectChat($alice->id, $bob->id);

        $this->actingAs($alice)->postJson("/api/rooms/{$dm->slug}/invite")->assertForbidden();

        $this->assertArrayNotHasKey('invite_link', $dm->fresh()->settings);
    }

    // ── Create: hanya user dengan role platform admin ──

    public function test_platform_admin_can_create_room_and_becomes_owner_and_admin(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->postJson('/api/rooms', [
            'name' => 'Mine',
            'slug' => 'mine-' . uniqid(),
            'type' => 'public',
        ])->assertCreated();

        $room = Room::where('owner_user_id', $admin->id)->firstOrFail();
        $this->assertDatabaseHas('discuss_members', [
            'room_id' => $room->id,
            'user_id' => $admin->id,
            'role' => 'admin',
        ]);
    }

    public function test_non_admin_users_cannot_create_room(): void
    {
        foreach (['reader', 'moderator', 'creator'] as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->actingAs($user)->postJson('/api/rooms', [
                'name' => 'Nope',
                'slug' => "nope-{$role}",
                'type' => 'public',
            ])->assertForbidden();

            $this->assertDatabaseMissing('discuss_rooms', ['slug' => "nope-{$role}"]);
        }
    }

    public function test_suspended_or_banned_admin_cannot_create_room(): void
    {
        foreach (['suspended', 'banned'] as $status) {
            $user = User::factory()->admin()->create(['status' => $status]);

            $this->actingAs($user)->postJson('/api/rooms', [
                'name' => 'Nope',
                'slug' => "nope-{$status}",
                'type' => 'public',
            ])->assertForbidden();

            $this->assertDatabaseMissing('discuss_rooms', ['slug' => "nope-{$status}"]);
        }
    }

    public function test_guest_cannot_create_room(): void
    {
        $this->postJson('/api/rooms', [
            'name' => 'Guest',
            'slug' => 'guest-room',
            'type' => 'public',
        ])->assertUnauthorized();
    }

    // ── Room komik: dibuat admin; user biasa tidak bisa membuatnya ──

    public function test_non_admin_cannot_open_a_comic_group_that_does_not_exist_yet(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/discuss/comic-solo-leveling?title=Solo+Leveling')
            ->assertSessionHasErrors('room');

        $this->assertDatabaseMissing('discuss_rooms', ['slug' => 'comic-solo-leveling']);
    }

    public function test_admin_opening_a_missing_comic_group_creates_it(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/discuss/comic-solo-leveling?title=Solo+Leveling')
            ->assertRedirect('/discuss/comic-solo-leveling');

        $room = Room::where('slug', 'comic-solo-leveling')->firstOrFail();
        $this->assertSame($admin->id, $room->owner_user_id);
        $this->assertSame('comic', $room->context_type);
    }

    // ── roomsForUser: discover list hanya room publik ──

    public function test_rooms_for_user_lists_only_public_rooms_for_guest_and_member(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $publicJoined = Room::factory()->create(['type' => 'public']);
        $publicNotJoined = Room::factory()->create(['type' => 'public']);
        $private = Room::factory()->create(['type' => 'private']);
        $inviteOnly = Room::factory()->create(['type' => 'invite_only']);
        $inactive = Room::factory()->create(['type' => 'public', 'is_active' => false]);
        $dm = app(RoomService::class)->findOrCreateDirectChat($user->id, $other->id);

        $this->addMember($publicJoined, $user);
        $this->addMember($private, $user);
        $this->addMember($inviteOnly, $user);

        $service = app(RoomService::class);

        $forUser = collect($service->roomsForUser($user->id))->pluck('id')->all();
        $forGuest = collect($service->roomsForUser(null))->pluck('id')->all();

        foreach ([$forUser, $forGuest] as $ids) {
            $this->assertContains($publicJoined->id, $ids);
            $this->assertContains($publicNotJoined->id, $ids);
            $this->assertNotContains($private->id, $ids);
            $this->assertNotContains($inviteOnly->id, $ids);
            $this->assertNotContains($inactive->id, $ids);
            $this->assertNotContains($dm->id, $ids);
        }
    }
}
