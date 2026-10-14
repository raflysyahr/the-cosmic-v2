<?php

namespace App\Modules\Auth\tests\Feature;

use App\Modules\Auth\Models\User;
use App\Modules\Discuss\Models\Member;
use App\Modules\Discuss\Models\Message;
use App\Modules\Discuss\Models\Room;
use App\Modules\Discuss\Services\RoomService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicProfileActivityTest extends TestCase
{
    use RefreshDatabase;

    private function image(Room $room, User $user, string $url): void
    {
        Message::create([
            'room_id' => $room->id, 'user_id' => $user->id, 'type' => 'image',
            'attachments' => [$url],
        ]);
    }

    public function test_content_is_split_into_group_and_private_chat(): void
    {
        $viewer = User::factory()->create();
        $target = User::factory()->create(['username' => 'janedoe']);

        $public = Room::factory()->create(['type' => 'public']);
        Member::factory()->create(['room_id' => $public->id, 'user_id' => $target->id]);

        $dm = app(RoomService::class)->findOrCreateDirectChat($viewer->id, $target->id);

        Message::create([
            'room_id' => $public->id, 'user_id' => $target->id, 'type' => 'video',
            'attachments' => ['/storage/v.mp4'],
            'metadata' => ['video' => ['duration' => 83.2, 'thumbnail' => '/storage/t.jpg']],
        ]);
        Message::create([
            'room_id' => $public->id, 'user_id' => $target->id, 'type' => 'text',
            'body' => 'cek https://www.example.com/a, ya',
        ]);
        $this->image($dm, $target, '/storage/dm.jpg');

        $this->actingAs($viewer)->get('/u/janedoe')->assertInertia(fn ($page) => $page
            ->component('Profile/Show')
            ->has('media.group', 1)
            ->where('media.group.0.type', 'video')
            ->where('media.group.0.roomName', $public->name)
            ->has('media.private', 1)
            ->where('media.private.0.url', '/storage/dm.jpg')
            ->has('links.group', 1)
            ->where('links.group.0.host', 'example.com')
            ->has('links.private', 0)
            ->has('voices.group', 0)
            ->has('voices.private', 0)
            ->has('groups', 1)
            ->where('groups.0.slug', $public->slug)
        );
    }

    public function test_other_peoples_direct_chats_never_leak(): void
    {
        $viewer = User::factory()->create();
        $target = User::factory()->create(['username' => 'janedoe']);
        $stranger = User::factory()->create();

        $dmOther = app(RoomService::class)->findOrCreateDirectChat($target->id, $stranger->id);
        $this->image($dmOther, $target, '/storage/secret.jpg');

        $this->actingAs($viewer)->get('/u/janedoe')->assertInertia(fn ($page) => $page
            ->has('media.group', 0)
            ->has('media.private', 0)
        );
    }

    public function test_private_group_visible_only_when_viewer_is_member(): void
    {
        $member = User::factory()->create();
        $outsider = User::factory()->create();
        $target = User::factory()->create(['username' => 'janedoe']);

        $secretGroup = Room::factory()->create(['type' => 'private']);
        Member::factory()->create(['room_id' => $secretGroup->id, 'user_id' => $target->id]);
        Member::factory()->create(['room_id' => $secretGroup->id, 'user_id' => $member->id]);
        $this->image($secretGroup, $target, '/storage/g.jpg');

        $this->actingAs($member)->get('/u/janedoe')->assertInertia(fn ($page) => $page
            ->has('media.group', 1)
            ->has('groups', 1)
        );

        $this->actingAs($outsider)->get('/u/janedoe')->assertInertia(fn ($page) => $page
            ->has('media.group', 0)
            ->has('groups', 0)
        );
    }

    public function test_own_profile_shows_all_own_direct_chats(): void
    {
        $me = User::factory()->create(['username' => 'me']);
        $a = User::factory()->create();
        $b = User::factory()->create();

        $svc = app(RoomService::class);
        $this->image($svc->findOrCreateDirectChat($me->id, $a->id), $me, '/storage/1.jpg');
        $this->image($svc->findOrCreateDirectChat($me->id, $b->id), $me, '/storage/2.jpg');

        $this->actingAs($me)->get('/u/me')->assertInertia(fn ($page) => $page
            ->has('media.private', 2)
        );
    }
}
