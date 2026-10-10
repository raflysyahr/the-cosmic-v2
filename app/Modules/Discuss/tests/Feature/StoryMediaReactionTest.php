<?php

namespace App\Modules\Discuss\tests\Feature;

use App\Modules\Auth\Models\User;
use App\Modules\Discuss\Models\Announcement;
use App\Modules\Discuss\Services\ImageThumbnailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Story: carousel foto/video + caption (admin), thumbnail foto, dan reaksi user. */
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

    private function items(string $id): array
    {
        return Announcement::findOrFail($id)->media_items ?? [];
    }

    // ----------------------------------------------------------- media

    public function test_admin_can_post_a_photo_with_only_a_caption(): void
    {
        $admin = User::factory()->admin()->create();

        $res = $this->actingAs($admin)->post('/api/admin/story', [
            'body' => 'Look at this!',
            'media' => [UploadedFile::fake()->image('pic.jpg', 600, 400)],
            'widths' => [600], 'heights' => [400],
        ], self::JSON)->assertStatus(201)
            ->assertJsonPath('announcement.title', '')
            ->assertJsonPath('announcement.body', 'Look at this!')
            ->assertJsonCount(1, 'announcement.media')
            ->assertJsonPath('announcement.media.0.type', 'image')
            ->assertJsonPath('announcement.media.0.width', 600);

        $item = $this->items($res->json('announcement.id'))[0];
        Storage::disk('public')->assertExists($item['path']);
        // Internal paths tidak bocor ke client.
        $this->assertArrayNotHasKey('path', $res->json('announcement.media.0'));
    }

    public function test_photos_get_a_smaller_feed_thumbnail_but_gifs_keep_the_original(): void
    {
        $admin = User::factory()->admin()->create();

        $res = $this->actingAs($admin)->post('/api/admin/story', [
            'body' => 'mixed',
            'media' => [
                UploadedFile::fake()->image('big.jpg', 2400, 1600),
                UploadedFile::fake()->image('anim.gif', 200, 200),
            ],
        ], self::JSON)->assertStatus(201);

        $jpg = $res->json('announcement.media.0');
        $gif = $res->json('announcement.media.1');

        $this->assertNotSame($jpg['url'], $jpg['thumbnail']);
        $this->assertStringContainsString('story/thumbs/', $jpg['thumbnail']);
        $thumbPath = $this->items($res->json('announcement.id'))[0]['thumb_path'];
        Storage::disk('public')->assertExists($thumbPath);
        [$w, $h] = getimagesizefromstring(Storage::disk('public')->get($thumbPath));
        $this->assertSame(1080, max($w, $h));

        // GIF: tanpa thumbnail, feed memakai file asli agar tetap beranimasi.
        $this->assertSame($gif['url'], $gif['thumbnail']);
        $this->assertNull($this->items($res->json('announcement.id'))[1]['thumb_path']);
    }

    public function test_admin_can_post_a_carousel_in_order_with_video_poster_and_duration(): void
    {
        $admin = User::factory()->admin()->create();

        $res = $this->actingAs($admin)->post('/api/admin/story', [
            'body' => 'Three slides',
            'media' => [
                UploadedFile::fake()->image('1.jpg', 800, 600),
                UploadedFile::fake()->create('2.mp4', 2000, 'video/mp4'),
                UploadedFile::fake()->image('3.png', 500, 500),
            ],
            'thumbnails' => [1 => UploadedFile::fake()->image('poster.jpg', 320, 180)],
            'durations' => [1 => 12.345],
            'widths' => [800, 1280, 500],
            'heights' => [600, 720, 500],
        ], self::JSON)->assertStatus(201)->assertJsonCount(3, 'announcement.media');

        $media = $res->json('announcement.media');
        $this->assertSame(['image', 'video', 'image'], array_column($media, 'type'));
        $this->assertSame(12.35, $media[1]['duration']);
        $this->assertSame(1280, $media[1]['width']);

        $items = $this->items($res->json('announcement.id'));
        Storage::disk('public')->assertExists($items[1]['path']);
        Storage::disk('public')->assertExists($items[1]['thumb_path']);
        // Id item unik & stabil untuk edit.
        $this->assertCount(3, array_unique(array_column($media, 'id')));
    }

    public function test_a_post_is_limited_to_ten_media(): void
    {
        $admin = User::factory()->admin()->create();

        $files = array_map(fn ($i) => UploadedFile::fake()->image("{$i}.jpg", 50, 50), range(1, 11));

        $this->actingAs($admin)->post('/api/admin/story', ['body' => 'too many', 'media' => $files], self::JSON)
            ->assertStatus(422)->assertJsonValidationErrors(['media']);

        $this->assertDatabaseCount('discuss_announcements', 0);
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
            'media' => [UploadedFile::fake()->create('evil.html', 10, 'text/html')],
        ], self::JSON)->assertStatus(422)->assertJsonValidationErrors(['media.0']);

        $this->actingAs($admin)->post('/api/admin/story', [
            'media' => [UploadedFile::fake()->image('huge.jpg')->size(9000)],
        ], self::JSON)->assertStatus(422)->assertJsonValidationErrors(['media.0']);
    }

    public function test_editing_can_keep_remove_and_add_media_and_cleans_up_files(): void
    {
        $admin = User::factory()->admin()->create();

        $created = $this->actingAs($admin)->post('/api/admin/story', [
            'body' => 'v1',
            'media' => [UploadedFile::fake()->image('a.jpg', 900, 900), UploadedFile::fake()->image('b.jpg', 900, 900)],
        ], self::JSON)->json('announcement');

        $id = $created['id'];
        [$a, $b] = $this->items($id);

        // Pertahankan b, buang a, tambah c (multipart via POST + _method=PUT).
        $res = $this->actingAs($admin)->post("/api/admin/story/{$id}", [
            '_method' => 'PUT',
            'sync_media' => '1',
            'keep_media' => [$b['id']],
            'media' => [UploadedFile::fake()->image('c.jpg', 900, 900)],
        ], self::JSON)->assertOk()->assertJsonCount(2, 'announcement.media')
            ->assertJsonPath('announcement.body', 'v1');

        $now = $this->items($id);
        $this->assertSame($b['id'], $now[0]['id']);
        $this->assertNotSame($a['id'], $now[1]['id']);

        Storage::disk('public')->assertMissing($a['path']);
        Storage::disk('public')->assertMissing($a['thumb_path']);
        Storage::disk('public')->assertExists($b['path']);
        Storage::disk('public')->assertExists($now[1]['path']);
    }

    public function test_editing_text_without_sync_keeps_all_media(): void
    {
        $admin = User::factory()->admin()->create();

        $id = $this->actingAs($admin)->post('/api/admin/story', [
            'body' => 'v1', 'media' => [UploadedFile::fake()->image('a.jpg', 100, 100)],
        ], self::JSON)->json('announcement.id');

        $this->actingAs($admin)->putJson("/api/admin/story/{$id}", ['body' => 'v2'])
            ->assertOk()->assertJsonPath('announcement.body', 'v2')->assertJsonCount(1, 'announcement.media');
    }

    public function test_a_post_cannot_be_edited_into_an_empty_one_and_new_files_are_not_orphaned(): void
    {
        $admin = User::factory()->admin()->create();

        $id = $this->actingAs($admin)->post('/api/admin/story', [
            'media' => [UploadedFile::fake()->image('a.jpg', 100, 100)],
        ], self::JSON)->json('announcement.id');
        $before = $this->items($id);

        // Hapus semua media + kosongkan teks → ditolak.
        $this->actingAs($admin)->post("/api/admin/story/{$id}", [
            '_method' => 'PUT', 'sync_media' => '1', 'title' => '', 'body' => '',
        ], self::JSON)->assertStatus(422)->assertJsonValidationErrors(['body']);

        $this->assertCount(1, $this->items($id));
        Storage::disk('public')->assertExists($before[0]['path']);
    }

    public function test_editing_beyond_ten_media_is_rejected_without_leaving_files(): void
    {
        $admin = User::factory()->admin()->create();

        $id = $this->actingAs($admin)->post('/api/admin/story', [
            'body' => 'x',
            'media' => array_map(fn ($i) => UploadedFile::fake()->image("{$i}.jpg", 50, 50), range(1, 10)),
        ], self::JSON)->assertStatus(201)->json('announcement.id');

        $this->actingAs($admin)->post("/api/admin/story/{$id}", [
            '_method' => 'PUT', 'media' => [UploadedFile::fake()->image('11.jpg', 50, 50)],
        ], self::JSON)->assertStatus(422)->assertJsonValidationErrors(['media']);

        $this->assertCount(10, $this->items($id));
        // 10 foto asli + 10 thumbnail; tidak ada file ke-11.
        $this->assertCount(10, Storage::disk('public')->allFiles('story/images'));
    }

    public function test_deleting_a_post_removes_all_files_and_reactions(): void
    {
        $admin = User::factory()->admin()->create();
        $user = $this->oldUser();

        $id = $this->actingAs($admin)->post('/api/admin/story', [
            'body' => 'bye',
            'media' => [UploadedFile::fake()->image('a.jpg', 900, 900), UploadedFile::fake()->image('b.jpg', 900, 900)],
        ], self::JSON)->json('announcement.id');
        $items = $this->items($id);

        $this->actingAs($user)->postJson("/api/discuss/story/{$id}/reaction", ['emoji' => '❤️'])->assertOk();

        $this->actingAs($admin)->deleteJson("/api/admin/story/{$id}")->assertOk();

        foreach ($items as $item) {
            Storage::disk('public')->assertMissing($item['path']);
            Storage::disk('public')->assertMissing($item['thumb_path']);
        }
        $this->assertDatabaseCount('discuss_announcement_reactions', 0);
    }

    public function test_regular_users_cannot_post_media(): void
    {
        $this->actingAs($this->oldUser())->post('/api/admin/story', [
            'body' => 'nope', 'media' => [UploadedFile::fake()->image('a.jpg')],
        ], self::JSON)->assertStatus(422);

        $this->assertDatabaseCount('discuss_announcements', 0);
    }

    public function test_thumbnail_command_backfills_old_photos_only_once(): void
    {
        $path = UploadedFile::fake()->image('old.jpg', 2000, 1000)->store('story/images', 'public');
        $post = $this->published(['media_items' => [[
            'id' => 'old1', 'type' => 'image', 'url' => Storage::url($path), 'thumbnail' => null,
            'width' => 2000, 'height' => 1000, 'duration' => null, 'name' => 'old.jpg', 'size' => 1,
            'mime' => 'image/jpeg', 'path' => $path, 'thumb_path' => null,
        ]]]);

        $this->artisan('discuss:story-thumbnails')->expectsOutputToContain('Thumbnails created: 1')->assertSuccessful();

        $item = $post->fresh()->media_items[0];
        $this->assertStringContainsString('story/thumbs/', $item['thumbnail']);
        Storage::disk('public')->assertExists($item['thumb_path']);

        $this->artisan('discuss:story-thumbnails')->expectsOutputToContain('Thumbnails created: 0')->assertSuccessful();
    }

    public function test_image_thumbnail_service_downsizes_and_rejects_garbage(): void
    {
        $service = app(ImageThumbnailService::class);

        $gd = imagecreatetruecolor(3000, 1500);
        ob_start();
        imagepng($gd);
        $png = (string) ob_get_clean();

        [$w, $h] = getimagesizefromstring((string) $service->make($png, 1080));
        $this->assertSame(1080, $w);
        $this->assertSame(540, $h);

        $this->assertNull($service->make('not an image'));
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

    public function test_feed_lists_only_reactions_above_zero_in_palette_order_and_my_reaction(): void
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

    public function test_reactor_identities_are_not_exposed_to_anyone(): void
    {
        $admin = User::factory()->admin()->create();
        $post = $this->published();

        $this->actingAs($this->oldUser())->postJson("/api/discuss/story/{$post->id}/reaction", ['emoji' => '😮'])->assertOk();

        $this->actingAs($admin)->getJson("/api/admin/story/{$post->id}/reactions")->assertNotFound();

        // Admin hanya melihat jumlah per emoji, tanpa identitas pengirim.
        $row = $this->actingAs($admin)->getJson('/api/admin/story')->assertOk()->json('announcements.0');
        $this->assertSame(1, $row['reaction_total']);
        $this->assertSame([['emoji' => '😮', 'count' => 1]], $row['reactions']);
        $this->assertArrayNotHasKey('reactors', $row);
    }
}
