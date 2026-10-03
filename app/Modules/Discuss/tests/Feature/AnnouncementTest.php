<?php

namespace App\Modules\Discuss\tests\Feature;

use App\Modules\Auth\Models\User;
use App\Modules\Discuss\Models\Announcement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Story = pemberitahuan dari admin; user hanya membaca. */
class AnnouncementTest extends TestCase
{
    use RefreshDatabase;

    private function make(string $title, array $overrides = []): Announcement
    {
        return Announcement::create(array_merge([
            'title' => $title,
            'body' => "Body of {$title}",
            'is_pinned' => false,
            'published_at' => now()->subHour(),
        ], $overrides));
    }

    private function oldUser(): User
    {
        return User::factory()->create(['created_at' => now()->subDays(30)]);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge(['title' => 'Maintenance tonight', 'body' => 'The site will be down at 22:00.'], $overrides);
    }

    public function test_users_see_only_published_announcements_pinned_first_then_newest(): void
    {
        $user = $this->oldUser();
        $this->make('Old', ['published_at' => now()->subDays(3)]);
        $this->make('Newest', ['published_at' => now()->subMinutes(5)]);
        $this->make('Pinned old', ['is_pinned' => true, 'published_at' => now()->subDays(10)]);
        $this->make('Scheduled', ['published_at' => now()->addDay()]);
        $this->make('Draft', ['published_at' => null]);

        $titles = collect($this->actingAs($user)->getJson('/api/discuss/story')->assertOk()->json('announcements'))->pluck('title')->all();

        $this->assertSame(['Pinned old', 'Newest', 'Old'], $titles);
    }

    public function test_new_flag_and_unread_count_follow_the_last_seen_time(): void
    {
        $user = $this->oldUser();
        $this->make('First');
        $this->make('Second');

        $this->actingAs($user)->getJson('/api/discuss/story/unread')->assertOk()->assertJsonPath('unread', 2);
        $list = $this->actingAs($user)->getJson('/api/discuss/story')->assertOk();
        $list->assertJsonPath('unread', 2);
        $this->assertTrue(collect($list->json('announcements'))->every(fn ($a) => $a['is_new']));

        $this->actingAs($user)->postJson('/api/discuss/story/seen')->assertOk()->assertJsonPath('unread', 0);
        $this->assertDatabaseHas('discuss_story_seen', ['user_id' => $user->id]);
        $this->actingAs($user)->getJson('/api/discuss/story/unread')->assertJsonPath('unread', 0);

        $this->travel(5)->minutes();
        $this->make('Third', ['published_at' => now()]);

        $this->actingAs($user)->getJson('/api/discuss/story/unread')->assertJsonPath('unread', 1);
        $flags = collect($this->actingAs($user)->getJson('/api/discuss/story')->json('announcements'))->pluck('is_new', 'title');
        $this->assertTrue($flags['Third']);
        $this->assertFalse($flags['First']);
    }

    public function test_announcements_from_before_a_user_joined_are_not_unread(): void
    {
        $this->make('Ancient', ['published_at' => now()->subDays(60)]);
        $newcomer = User::factory()->create(['created_at' => now()->subDay()]);

        $this->actingAs($newcomer)->getJson('/api/discuss/story/unread')->assertJsonPath('unread', 0);
        $this->actingAs($newcomer)->getJson('/api/discuss/story')
            ->assertJsonPath('announcements.0.title', 'Ancient')
            ->assertJsonPath('announcements.0.is_new', false);
    }

    public function test_scheduled_announcement_is_not_counted_or_listed_until_its_time(): void
    {
        $user = $this->oldUser();
        $this->make('Later', ['published_at' => now()->addHours(2)]);

        $this->actingAs($user)->getJson('/api/discuss/story/unread')->assertJsonPath('unread', 0);

        $this->travel(3)->hours();

        $this->actingAs($user)->getJson('/api/discuss/story/unread')->assertJsonPath('unread', 1);
    }

    public function test_guests_cannot_read_announcements(): void
    {
        $this->getJson('/api/discuss/story')->assertStatus(401);
        $this->getJson('/api/discuss/story/unread')->assertStatus(401);
        $this->postJson('/api/discuss/story/seen')->assertStatus(401);
    }

    public function test_admin_can_create_update_and_delete(): void
    {
        $admin = User::factory()->admin()->create();

        $id = $this->actingAs($admin)->postJson('/api/admin/story', $this->payload(['is_pinned' => true, 'link_url' => 'https://example.com/news']))
            ->assertStatus(201)
            ->assertJsonPath('announcement.title', 'Maintenance tonight')
            ->assertJsonPath('announcement.is_pinned', true)
            ->assertJsonPath('announcement.is_published', true)
            ->json('announcement.id');

        $this->assertDatabaseHas('discuss_announcements', ['id' => $id, 'created_by' => $admin->id, 'link_url' => 'https://example.com/news']);
        $this->assertNotNull(Announcement::find($id)->published_at);

        $this->actingAs($admin)->putJson("/api/admin/story/{$id}", ['title' => 'Maintenance moved', 'is_pinned' => false, 'link_url' => ''])
            ->assertOk()
            ->assertJsonPath('announcement.title', 'Maintenance moved')
            ->assertJsonPath('announcement.is_pinned', false)
            ->assertJsonPath('announcement.link_url', null)
            ->assertJsonPath('announcement.body', 'The site will be down at 22:00.');

        $this->actingAs($admin)->deleteJson("/api/admin/story/{$id}")->assertOk();
        $this->assertDatabaseMissing('discuss_announcements', ['id' => $id]);
    }

    public function test_admin_list_includes_scheduled_announcements(): void
    {
        $admin = User::factory()->admin()->create();
        $this->make('Live');
        $this->make('Soon', ['published_at' => now()->addDay()]);

        $rows = collect($this->actingAs($admin)->getJson('/api/admin/story')->assertOk()->json('announcements'))->pluck('is_published', 'title');

        $this->assertTrue($rows['Live']);
        $this->assertFalse($rows['Soon']);
    }

    public function test_admin_can_schedule_for_the_future(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->postJson('/api/admin/story', $this->payload(['published_at' => now()->addDay()->toIso8601String()]))
            ->assertStatus(201)
            ->assertJsonPath('announcement.is_published', false);

        $this->actingAs($this->oldUser())->getJson('/api/discuss/story')->assertJsonCount(0, 'announcements');
    }

    public function test_validation_rejects_bad_input_including_unsafe_links(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->postJson('/api/admin/story', ['body' => 'No title'])
            ->assertStatus(422)->assertJsonValidationErrors(['title']);
        $this->actingAs($admin)->postJson('/api/admin/story', $this->payload(['link_url' => 'javascript:alert(1)']))
            ->assertStatus(422)->assertJsonValidationErrors(['link_url']);
        $this->actingAs($admin)->postJson('/api/admin/story', $this->payload(['title' => str_repeat('a', 121)]))
            ->assertStatus(422)->assertJsonValidationErrors(['title']);

        $this->assertDatabaseCount('discuss_announcements', 0);
    }

    public function test_regular_users_cannot_manage_announcements(): void
    {
        $user = User::factory()->create();
        $existing = $this->make('Existing');

        $this->actingAs($user)->getJson('/api/admin/story')->assertStatus(422)->assertJsonValidationErrors(['admin']);
        $this->actingAs($user)->postJson('/api/admin/story', $this->payload())->assertStatus(422);
        $this->actingAs($user)->putJson("/api/admin/story/{$existing->id}", ['title' => 'Hacked'])->assertStatus(422);
        $this->actingAs($user)->deleteJson("/api/admin/story/{$existing->id}")->assertStatus(422);

        $this->assertSame('Existing', $existing->fresh()->title);
        $this->assertDatabaseCount('discuss_announcements', 1);
    }

    public function test_story_page_renders_and_flags_admins(): void
    {
        $this->actingAs(User::factory()->create())->get('/discuss/story')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Discuss/Story')->where('isAdmin', false));

        $this->actingAs(User::factory()->admin()->create())->get('/discuss/story')
            ->assertInertia(fn ($page) => $page->where('isAdmin', true));
    }
}
