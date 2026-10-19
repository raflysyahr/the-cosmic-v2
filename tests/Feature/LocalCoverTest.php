<?php

namespace Tests\Feature;

use App\Models\Bookmark;
use App\Models\ReadingHistory;
use App\Models\Series;
use App\Modules\Auth\Models\User;
use App\Modules\Discuss\Models\Room;
use App\Modules\Discuss\Services\RoomService;
use App\Services\BookmarkService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LocalCoverTest extends TestCase
{
    use RefreshDatabase;

    private const OLD = 'https://cdn.example/old.jpg';
    private const LOCAL = 'https://app.test/api/v1/series/solo/cover';

    private function series(string $slug, ?string $cover = self::LOCAL): Series
    {
        return Series::create(['slug' => $slug, 'title' => ucfirst($slug), 'cover' => $cover, 'status' => 'ongoing', 'type' => 'manga']);
    }

    private function bookmark(User $user, string $slug, string $cover = self::OLD): Bookmark
    {
        return Bookmark::create(['user_id' => $user->id, 'slug' => $slug, 'title' => $slug, 'cover_image' => $cover, 'format' => 'manga']);
    }

    // ── Bookmark ──

    public function test_bookmarks_show_the_local_series_cover_without_any_network_call(): void
    {
        Http::fake();
        $user = User::factory()->create();
        $this->series('solo');
        $this->bookmark($user, 'solo');

        $response = $this->actingAs($user)->getJson('/api/bookmarks')->assertOk();
        $this->app->terminate();

        $this->assertSame(self::LOCAL, $response->json('data.0.cover_image'));
        Http::assertNothingSent();
    }

    public function test_stored_bookmark_cover_is_not_modified(): void
    {
        $user = User::factory()->create();
        $this->series('solo');
        $this->bookmark($user, 'solo');

        app(BookmarkService::class)->list($user->id);

        $this->assertSame(self::OLD, Bookmark::first()->cover_image);
    }

    public function test_bookmark_falls_back_to_the_stored_cover_when_series_or_cover_is_missing(): void
    {
        $user = User::factory()->create();
        $this->series('no-cover', cover: null);
        $this->series('empty-cover', cover: '');
        $this->bookmark($user, 'no-cover');
        $this->bookmark($user, 'empty-cover');
        $this->bookmark($user, 'unknown-series');

        $covers = app(BookmarkService::class)->list($user->id)->pluck('cover_image')->unique()->all();

        $this->assertSame([self::OLD], $covers);
    }

    public function test_listing_bookmarks_uses_a_constant_number_of_queries(): void
    {
        $user = User::factory()->create();
        foreach (range(1, 12) as $i) {
            $this->series("series-{$i}");
            $this->bookmark($user, "series-{$i}");
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        app(BookmarkService::class)->list($user->id);
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame(2, $count); // bookmark + series
    }

    // ── Riwayat baca ──

    public function test_recent_reading_history_uses_the_local_cover_and_falls_back(): void
    {
        Http::fake();
        $user = User::factory()->create();
        $this->series('solo');
        ReadingHistory::create(['user_id' => $user->id, 'slug' => 'solo', 'title' => 'Solo', 'cover_image' => self::OLD, 'last_chapter_index' => 3]);
        ReadingHistory::create(['user_id' => $user->id, 'slug' => 'ghost', 'title' => 'Ghost', 'cover_image' => self::OLD, 'last_chapter_index' => 1]);

        $data = collect($this->actingAs($user)->getJson('/api/reading-history/recent')->assertOk()->json('data'))->keyBy('slug');
        $this->app->terminate();

        $this->assertSame(self::LOCAL, $data['solo']['coverImage']);
        $this->assertSame(self::OLD, $data['ghost']['coverImage']);
        Http::assertNothingSent();
    }

    // ── Room komik ──

    public function test_comic_room_cover_comes_from_the_local_series_table(): void
    {
        Http::fake();
        $this->series('solo');
        $room = Room::factory()->create(['type' => 'public', 'context_type' => 'comic', 'context_id' => 'solo', 'cover_url' => self::OLD]);

        $this->assertSame(self::LOCAL, app(RoomService::class)->freshCover($room));
        $this->actingAs(User::factory()->create())->getJson("/api/rooms/{$room->slug}")
            ->assertOk()
            ->assertJsonPath('data.cover_url', self::LOCAL);
        Http::assertNothingSent();
    }

    public function test_room_cover_falls_back_to_the_stored_one(): void
    {
        $service = app(RoomService::class);

        $normal = Room::factory()->create(['type' => 'public', 'cover_url' => self::OLD]);
        $missingSeries = Room::factory()->create(['type' => 'public', 'context_type' => 'comic', 'context_id' => 'ghost', 'cover_url' => self::OLD]);

        $this->assertSame(self::OLD, $service->freshCover($normal));
        $this->assertSame(self::OLD, $service->freshCover($missingSeries));
    }

    public function test_series_covers_for_ignores_blank_input(): void
    {
        $this->series('solo');

        $this->assertSame([], Series::coversFor([]));
        $this->assertSame([], Series::coversFor(['', null]));
        $this->assertSame(['solo' => self::LOCAL], Series::coversFor(['solo', 'solo', 'nope']));
    }
}
