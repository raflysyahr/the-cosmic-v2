<?php

namespace App\Modules\Auth\tests\Feature;

use App\Modules\Auth\Models\User;
use App\Modules\Auth\Models\UserProfile;
use App\Modules\Discuss\Data\SendMessageData;
use App\Modules\Discuss\Models\Member;
use App\Modules\Discuss\Models\Room;
use App\Modules\Discuss\Services\MessageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_view_another_users_public_profile(): void
    {
        $viewer = User::factory()->create();
        $target = User::factory()->create([
            'username' => 'janedoe',
            'display_name' => 'Jane Doe',
        ]);

        $response = $this->actingAs($viewer)->get('/u/janedoe');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Profile/Show')
            ->where('profile.username', 'janedoe')
            ->where('profile.displayName', 'Jane Doe')
            ->where('isSelf', false)
        );
    }

    public function test_public_profile_does_not_expose_email(): void
    {
        $viewer = User::factory()->create();
        $target = User::factory()->create(['username' => 'janedoe']);

        $response = $this->actingAs($viewer)->get('/u/janedoe');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Profile/Show')
            ->missing('profile.email')
        );
    }

    public function test_public_profile_does_not_expose_bookmarks_stat(): void
    {
        $viewer = User::factory()->create();
        $target = User::factory()->create(['username' => 'janedoe']);

        $response = $this->actingAs($viewer)->get('/u/janedoe');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->missing('profile.stats.bookmarks')
        );
    }

    public function test_public_profile_includes_message_and_room_stats(): void
    {
        $viewer = User::factory()->create();
        $target = User::factory()->create(['username' => 'janedoe']);
        $room = Room::factory()->create(['type' => 'public']);

        Member::create([
            'room_id' => $room->id,
            'user_id' => $target->id,
            'role' => 'member',
            'xp_points' => 42,
            'is_banned' => false,
            'joined_at' => now(),
            'last_read_at' => now(),
        ]);

        app(MessageService::class)->send(new SendMessageData(
            roomId: $room->id,
            userId: $target->id,
            replyToId: null,
            type: 'text',
            body: 'Hello',
            attachments: [],
            metadata: [],
        ));

        $response = $this->actingAs($viewer)->get('/u/janedoe');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->where('profile.stats.messages', 1)
            ->where('profile.stats.rooms', 1)
            ->where('profile.stats.xp', 42)
        );
    }

    public function test_viewing_own_profile_sets_is_self_true(): void
    {
        $user = User::factory()->create(['username' => 'myself']);

        $response = $this->actingAs($user)->get('/u/myself');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->where('isSelf', true));
    }

    public function test_unknown_username_404s(): void
    {
        $viewer = User::factory()->create();

        $response = $this->actingAs($viewer)->get('/u/does-not-exist');

        $response->assertStatus(404);
    }

    public function test_guest_cannot_view_public_profile(): void
    {
        $target = User::factory()->create(['username' => 'janedoe']);

        $response = $this->get('/u/janedoe');

        $response->assertRedirect('/login');
    }

    public function test_public_profile_includes_bio_when_set(): void
    {
        $viewer = User::factory()->create();
        $target = User::factory()->create(['username' => 'janedoe']);

        UserProfile::create([
            'user_id' => $target->id,
            'bio' => 'I love comics!',
            'website_url' => 'https://example.com',
            'location' => 'Jakarta',
        ]);

        $response = $this->actingAs($viewer)->get('/u/janedoe');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->where('profile.bio', 'I love comics!')
            ->where('profile.websiteUrl', 'https://example.com')
            ->where('profile.location', 'Jakarta')
        );
    }
}
