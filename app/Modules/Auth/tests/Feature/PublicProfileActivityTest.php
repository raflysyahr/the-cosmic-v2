<?php

namespace App\Modules\Auth\tests\Feature;

use App\Modules\Auth\Models\User;
use App\Modules\Discuss\Models\Member;
use App\Modules\Discuss\Models\Message;
use App\Modules\Discuss\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicProfileActivityTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_lists_media_links_and_groups_from_public_rooms_only(): void
    {
        $viewer = User::factory()->create();
        $target = User::factory()->create(['username' => 'janedoe']);

        $public = Room::factory()->create(['type' => 'public', 'is_active' => true]);
        $private = Room::factory()->create(['type' => 'private', 'is_active' => true]);

        foreach ([$public, $private] as $room) {
            Member::factory()->create(['room_id' => $room->id, 'user_id' => $target->id]);
        }

        Message::create([
            'room_id' => $public->id, 'user_id' => $target->id, 'type' => 'video',
            'attachments' => ['/storage/v.mp4'],
            'metadata' => ['video' => ['duration' => 83.2, 'thumbnail' => '/storage/t.jpg']],
        ]);
        Message::create([
            'room_id' => $public->id, 'user_id' => $target->id, 'type' => 'text',
            'body' => 'cek https://www.example.com/a, ya',
        ]);
        // Harus TIDAK muncul: dari room private.
        Message::create([
            'room_id' => $private->id, 'user_id' => $target->id, 'type' => 'image',
            'attachments' => ['/storage/secret.jpg'],
        ]);

        $this->actingAs($viewer)->get('/u/janedoe')->assertInertia(fn ($page) => $page
            ->component('Profile/Show')
            ->has('media', 1)
            ->where('media.0.type', 'video')
            ->where('media.0.duration', 83.2)
            ->has('links', 1)
            ->where('links.0.host', 'example.com')
            ->has('groups', 1)
            ->where('groups.0.slug', $public->slug)
        );
    }
}
