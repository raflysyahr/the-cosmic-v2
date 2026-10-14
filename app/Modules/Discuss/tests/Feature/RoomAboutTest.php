<?php

namespace App\Modules\Discuss\tests\Feature;

use App\Modules\Auth\Models\User;
use App\Modules\Discuss\Models\Member;
use App\Modules\Discuss\Models\Message;
use App\Modules\Discuss\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoomAboutTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_view_about_page_of_public_room(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->create(['type' => 'public']);

        Member::create([
            'room_id' => $room->id,
            'user_id' => $user->id,
            'role' => 'member',
            'is_banned' => false,
            'joined_at' => now(),
            'last_read_at' => now(),
        ]);

        $response = $this->actingAs($user)->get("/discuss/{$room->slug}/about");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Discuss/About')
            ->where('room.slug', $room->slug)
        );
    }

    public function test_non_member_cannot_view_about_page_of_private_room(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->create(['type' => 'private']);

        $response = $this->actingAs($user)->get("/discuss/{$room->slug}/about");

        $response->assertStatus(403);
    }

    public function test_about_page_includes_member_list_with_username(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create(['username' => 'otheruser']);
        $room = Room::factory()->create(['type' => 'public']);

        Member::create([
            'room_id' => $room->id,
            'user_id' => $user->id,
            'role' => 'admin',
            'is_banned' => false,
            'joined_at' => now(),
            'last_read_at' => now(),
        ]);
        Member::create([
            'room_id' => $room->id,
            'user_id' => $other->id,
            'role' => 'member',
            'is_banned' => false,
            'joined_at' => now(),
            'last_read_at' => now(),
        ]);

        $response = $this->actingAs($user)->get("/discuss/{$room->slug}/about");

        $response->assertStatus(200);

        $members = $response->getOriginalContent()->getData()['page']['props']['members'];
        $usernames = collect($members)->pluck('username')->all();

        $this->assertContains('otheruser', $usernames);
    }

    public function test_unknown_room_slug_404s(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/discuss/does-not-exist/about');

        $response->assertStatus(404);
    }

    public function test_guest_cannot_view_about_page(): void
    {
        $room = Room::factory()->create(['type' => 'public']);

        $response = $this->get("/discuss/{$room->slug}/about");

        $response->assertRedirect('/login');
    }

    public function test_about_page_lists_only_this_rooms_media_links_and_voice(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->create(['type' => 'public']);
        $otherRoom = Room::factory()->create(['type' => 'public']);

        Member::create([
            'room_id' => $room->id, 'user_id' => $user->id, 'role' => 'member',
            'is_banned' => false, 'joined_at' => now(), 'last_read_at' => now(),
        ]);

        Message::create([
            'room_id' => $room->id, 'user_id' => $user->id, 'type' => 'video',
            'attachments' => ['/storage/v.mp4'],
            'metadata' => ['video' => ['duration' => 42.5, 'thumbnail' => '/storage/t.jpg']],
        ]);
        Message::create([
            'room_id' => $room->id, 'user_id' => $user->id, 'type' => 'text',
            'body' => 'baca https://www.example.com/post.',
        ]);
        Message::create([
            'room_id' => $room->id, 'user_id' => $user->id, 'type' => 'file',
            'attachments' => ['/storage/a.mp3'],
            'metadata' => ['file' => ['name' => 'a.mp3', 'mime' => 'audio/mpeg', 'size' => 1234]],
        ]);
        // Harus TIDAK muncul: room lain.
        Message::create([
            'room_id' => $otherRoom->id, 'user_id' => $user->id, 'type' => 'image',
            'attachments' => ['/storage/other.jpg'],
        ]);

        $this->actingAs($user)->get("/discuss/{$room->slug}/about")->assertInertia(fn ($page) => $page
            ->component('Discuss/About')
            ->has('media', 1)
            ->where('media.0.type', 'video')
            ->where('media.0.duration', 42.5)
            ->where('media.0.senderName', $user->display_name)
            ->has('links', 1)
            ->where('links.0.host', 'example.com')
            ->has('voices', 1)
            ->where('voices.0.name', 'a.mp3')
            ->where('stats.members', 1)
            ->where('stats.messages', 3)
        );
    }
}
