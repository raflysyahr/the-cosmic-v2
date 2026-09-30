<?php

namespace App\Modules\Discuss\tests\Feature;

use App\Modules\Auth\Models\User;
use App\Modules\Discuss\Models\Emote;
use App\Modules\Discuss\Models\Member;
use App\Modules\Discuss\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class EmoteControllerTest extends TestCase
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

    public function test_can_list_emotes_for_room(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->create();
        Emote::create(['room_id' => $room->id, 'code' => ':wave:', 'name' => 'Wave', 'image_url' => '/e/wave.png']);

        $response = $this->actingAs($user)->getJson("/api/emotes?room_id={$room->id}");

        $response->assertStatus(200);
        $response->assertJsonCount(1);
    }

    public function test_moderator_can_upload_room_emote(): void
    {
        $moderator = User::factory()->create();
        $room = Room::factory()->create();
        $this->addMember($room, $moderator, 'moderator');

        \Illuminate\Support\Facades\Storage::fake('public');

        $response = $this->actingAs($moderator)->postJson('/api/emotes', [
            'code' => ':wave:',
            'name' => 'Wave',
            'image' => UploadedFile::fake()->image('wave.png'),
            'room_id' => $room->id,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('discuss_emotes', ['code' => ':wave:', 'room_id' => $room->id]);
    }

    public function test_regular_member_cannot_upload_room_emote(): void
    {
        $regular = User::factory()->create();
        $room = Room::factory()->create();
        $this->addMember($room, $regular);

        \Illuminate\Support\Facades\Storage::fake('public');

        $response = $this->actingAs($regular)->postJson('/api/emotes', [
            'code' => ':wave:',
            'name' => 'Wave',
            'image' => UploadedFile::fake()->image('wave.png'),
            'room_id' => $room->id,
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('discuss_emotes', ['code' => ':wave:']);
    }

    public function test_non_admin_cannot_upload_global_emote(): void
    {
        $user = User::factory()->create(['role' => 'reader']);

        \Illuminate\Support\Facades\Storage::fake('public');

        $response = $this->actingAs($user)->postJson('/api/emotes', [
            'code' => ':global:',
            'name' => 'Global',
            'image' => UploadedFile::fake()->image('global.png'),
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('discuss_emotes', ['code' => ':global:']);
    }

    public function test_site_admin_can_upload_global_emote(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        \Illuminate\Support\Facades\Storage::fake('public');

        $response = $this->actingAs($admin)->postJson('/api/emotes', [
            'code' => ':global:',
            'name' => 'Global',
            'image' => UploadedFile::fake()->image('global.png'),
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('discuss_emotes', ['code' => ':global:', 'room_id' => null]);
    }

    public function test_moderator_can_deactivate_room_emote(): void
    {
        $moderator = User::factory()->create();
        $room = Room::factory()->create();
        $this->addMember($room, $moderator, 'moderator');
        $emote = Emote::create(['room_id' => $room->id, 'code' => ':wave:', 'name' => 'Wave', 'image_url' => '/e/wave.png']);

        $response = $this->actingAs($moderator)->deleteJson("/api/emotes/{$emote->id}");

        $response->assertStatus(200);
        $this->assertFalse($emote->fresh()->is_active);
    }

    public function test_regular_member_cannot_deactivate_room_emote(): void
    {
        $regular = User::factory()->create();
        $room = Room::factory()->create();
        $this->addMember($room, $regular);
        $emote = Emote::create(['room_id' => $room->id, 'code' => ':wave:', 'name' => 'Wave', 'image_url' => '/e/wave.png']);

        $response = $this->actingAs($regular)->deleteJson("/api/emotes/{$emote->id}");

        $response->assertStatus(422);
        $this->assertTrue($emote->fresh()->is_active);
    }
}
