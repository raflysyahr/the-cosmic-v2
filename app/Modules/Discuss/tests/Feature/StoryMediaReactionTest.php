<?php

namespace App\Modules\Discuss\tests\Feature;

use App\Modules\Auth\Models\User;
use App\Modules\Discuss\Models\Announcement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Story: post foto/video + caption (admin) dan reaksi user. */
class StoryMediaReactionTest extends TestCase
{
    use RefreshDatabase;

    private const JSON = ['Accept' => 'application/json'];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function published(array $overrides = []): Announcement
    {
        return Announcement::create(array_merge([
            'title' => 'Hello', 'body' => 'World', 'is_pinned' => false, 'published_at' => now()->subHour(),
        ], $overrides));
    }

    private function oldUser(): User
    {
        return User::factory()->create(['created_at' => now()->subDays(30)]);
    }

    // ----------------------------------------------------------- media

    public function test_admin_can_post_a_photo_with_only_a_caption(): void
    {
        $admin = User::factory()->admin()->create();

        $res = $this->actingAs($admin)->post('/api/admin/story', [
            'body' => 'Look at this!',
            'media' => UploadedFile::fake()->image('pic.jpg', 600, 400),
            'width' => 600, 'height' => 400,
        ], self::JSON)->assertStatus(201)
            ->assertJsonPath('announcement.title', '')
            ->assertJsonPath('announcement.body', 'Look at this!')
            ->assertJsonPath('announcement.media.type', 'image')
            ->assertJsonPath('announcement.media.width', 600);

        $path = Announcement::first()->media_meta['path'];
        Storage::disk('public')->assertExists($path);
        $this->assertSame($res->json('announcement.media.url'), $res->json('announcement.media.thumbnail'));
    }

    public function test_admin_can_post_a_video_with_poster_and_duration(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/api/admin/story', [
            'media' => UploadedFile::fake()->create('clip.mp4', 2000, 'video/mp4'),
            'thumbnail' => UploadedFile::fake()->image('poster.jpg', 320, 180),
            'duration' => 12.345, 'width' => 1280, 'height' => 720,
        ], self::JSON)->assertStatus(201)
            ->assertJsonPath('announcement.media.type', 'video')
            ->assertJsonPath('announcement.media.duration', 12.35)
            ->assertJsonPath('announcement.body', '');

        $meta = Announcement::first()->media_meta;
        Storage::disk('public')->assertExists($meta['path']);
        Storage::disk('public')->assertExists($meta['thumb_path']);
    }

    public function test_a_post_needs_text_or_media(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->postJson('/api/admin/story', ['is_pinned' => true])
            ->assertStatus(422)->assertJsonValidationErrors(['title', 'body']);
    }

    public function test_media_validation_rejects_bad_types_and_oversized_photos(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/api/admin/story', [
            'media' => UploadedFile::fake()->create('evil.html', 10, 'text/html'),
        ], self::JSON)->assertStatus(422)->assertJsonValidationErrors(['media']);

        $this->actingAs($admin)->post('/api/admin/story', [
            'media' => UploadedFile::fake()->image('huge.jpg')->size(9000),
        ], self::JSON)->assertStatus(422)->assertJsonValidationErrors(['media']);
    }

    public function test_replacing_or_removing_media_deletes_the_old_file(): void
    {
        $admin = User::factory()->admin()->create();

        $id = $this->actingAs($admin)->post('/api/admin/story', [
            'body' => 'v1', 'media' => UploadedFile::fake()->image('a.jpg'),
        ], self::JSON)->json('announcement.id');
        $first = Announcement::find($id)->media_meta['path'];

        // Ganti media lewat POST + _method=PUT (multipart).
        $this->actingAs($admin)->post("/api/admin/story/{$id}", [
            '_method' => 'PUT', 'media' => UploadedFile::fake()->image('b.jpg'),
        ], self::JSON)->assertOk()->assertJsonPath('announcement.body', 'v1');
        $second = Announcement::find($id)->media_meta['path'];

        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);

        $this->actingAs($admin)->putJson("/api/admin/story/{$id}", ['remove_media' => true])
            ->assertOk()->assertJsonPath('announcement.media', null);
        Storage::disk('public')->assertMissing($second);
    }

    public function test_deleting_a_post_removes_its_files_and_reactions(): void
    {
        $admin = User::factory()->admin()->create();
        $user = $this->oldUser();

        $id = $this->actingAs($admin)->post('/api/admin/story', [
            'body' => 'bye', 'media' => UploadedFile::fake()->image('a.jpg'),
        ], self::JSON)->json('announcement.id');
        $path = Announcement::find($id)->media_meta['path'];

        $this->actingAs($user)->postJson("/api/discuss/story/{$id}/reaction", ['emoji' => '❤️'])->assertOk();

        $this->actingAs($admin)->deleteJson("/api/admin/story/{$id}")->assertOk();

        Storage::disk('public')->assertMissing($path);
        $this->assertDatabaseCount('discuss_announcement_reactions', 0);
    }

    public function test_regular_users_cannot_post_media(): void
    {
        $this->actingAs($this->oldUser())->post('/api/admin/story', [
            'body' => 'nope', 'media' => UploadedFile::fake()->image('a.jpg'),
        ], self::JSON)->assertStatus(422);

        $this->assertDatabaseCount('discuss_announcements', 0);
    }

    // ----------------------------------------------------------- reaksi

    public function test_user_can_react_switch_and_remove_a_reaction(): void
    {
        $user = $this->oldUser();
        $post = $this->published();

        $this->actingAs($user)->postJson("/api/discuss/story/{$post->id}/reaction", ['emoji' => '🔥'])
            ->assertOk()
            ->assertJsonPath('my_reaction', '🔥')
            ->assertJsonPath('reaction_total', 1)
            ->assertJsonPath('reactions.0.emoji', '🔥');

        // Emoji lain = mengganti, bukan menambah.
        $this->actingAs($user)->postJson("/api/discuss/story/{$post->id}/reaction", ['emoji' => '😂'])
            ->assertJsonPath('my_reaction', '😂')
            ->assertJsonPath('reaction_total', 1);
        $this->assertDatabaseCount('discuss_announcement_reactions', 1);

        // Emoji yang sama = dicabut.
        $this->actingAs($user)->postJson("/api/discuss/story/{$post->id}/reaction", ['emoji' => '😂'])
            ->assertJsonPath('my_reaction', null)
            ->assertJsonPath('reaction_total', 0);
        $this->assertDatabaseCount('discuss_announcement_reactions', 0);
    }

    public function test_feed_shows_counts_in_palette_order_and_my_reaction(): void
    {
        $post = $this->published();
        $me = $this->oldUser();

        foreach ([[$me, '👍'], [$this->oldUser(), '❤️'], [$this->oldUser(), '❤️']] as [$u, $emoji]) {
            $this->actingAs($u)->postJson("/api/discuss/story/{$post->id}/reaction", ['emoji' => $emoji])->assertOk();
        }

        $row = $this->actingAs($me)->getJson('/api/discuss/story')->assertOk()->json('announcements.0');

        $this->assertSame(3, $row['reaction_total']);
        $this->assertSame('👍', $row['my_reaction']);
        $this->assertSame([['emoji' => '❤️', 'count' => 2], ['emoji' => '👍', 'count' => 1]], $row['reactions']);
    }

    public function test_reaction_rejects_unknown_emoji_and_unpublished_posts(): void
    {
        $user = $this->oldUser();
        $post = $this->published();
        $scheduled = $this->published(['published_at' => now()->addDay()]);

        $this->actingAs($user)->postJson("/api/discuss/story/{$post->id}/reaction", ['emoji' => '💩'])
            ->assertStatus(422)->assertJsonValidationErrors(['emoji']);

        $this->actingAs($user)->postJson("/api/discuss/story/{$scheduled->id}/reaction", ['emoji' => '❤️'])
            ->assertNotFound();
    }

    public function test_guests_cannot_react(): void
    {
        $post = $this->published();

        $this->postJson("/api/discuss/story/{$post->id}/reaction", ['emoji' => '❤️'])->assertStatus(401);
    }

    public function test_only_admin_can_see_who_reacted(): void
    {
        $admin = User::factory()->admin()->create();
        $alice = User::factory()->create(['display_name' => 'Alice', 'created_at' => now()->subDays(30)]);
        $post = $this->published();

        $this->actingAs($alice)->postJson("/api/discuss/story/{$post->id}/reaction", ['emoji' => '😮'])->assertOk();

        $this->actingAs($admin)->getJson("/api/admin/story/{$post->id}/reactions")
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('reactors.0.display_name', 'Alice')
            ->assertJsonPath('reactors.0.emoji', '😮')
            ->assertJsonPath('reactions.0.count', 1);

        $this->actingAs($alice)->getJson("/api/admin/story/{$post->id}/reactions")
            ->assertStatus(422)->assertJsonValidationErrors(['admin']);
    }
}
