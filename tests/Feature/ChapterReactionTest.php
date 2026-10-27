<?php

namespace Tests\Feature;

use App\Models\Chapter;
use App\Models\ChapterReaction;
use App\Models\Series;
use App\Modules\Auth\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Reaksi untuk HALAMAN chapter (tabel `chapter_reactions`), terpisah dari reaksi pesan Discuss.
 */
class ChapterReactionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $series = Series::create(['slug' => 'solo-leveling', 'title' => 'Solo Leveling']);
        foreach ([1, 2] as $i) {
            Chapter::create(['series_id' => $series->id, 'index' => $i, 'title' => "Chapter {$i}"]);
        }
    }

    private function url(int $chapter = 1, string $slug = 'solo-leveling'): string
    {
        return "/api/series/{$slug}/chapters/{$chapter}/reactions";
    }

    public function test_guests_can_see_counts_but_not_react(): void
    {
        ChapterReaction::create([
            'series_slug' => 'solo-leveling', 'chapter_index' => 1,
            'user_id' => User::factory()->create()->id, 'reaction' => 'like',
        ]);

        $this->getJson($this->url())
            ->assertOk()
            ->assertExactJson([
                'reactions' => [['key' => 'like', 'count' => 1]],
                'my_reaction' => null,
                'total' => 1,
            ]);

        $this->postJson($this->url(), ['reaction' => 'like'])->assertUnauthorized();
    }

    public function test_an_empty_chapter_has_no_reactions(): void
    {
        $this->getJson($this->url())->assertOk()->assertExactJson([
            'reactions' => [], 'my_reaction' => null, 'total' => 0,
        ]);
    }

    public function test_reacting_adds_pressing_again_removes_and_another_key_replaces(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson($this->url(), ['reaction' => 'like'])
            ->assertOk()
            ->assertExactJson(['reactions' => [['key' => 'like', 'count' => 1]], 'my_reaction' => 'like', 'total' => 1]);

        // Ganti: jumlah 'like' turun, 'fire' naik, tetap satu reaksi per user.
        $this->actingAs($user)->postJson($this->url(), ['reaction' => 'fire'])
            ->assertOk()
            ->assertExactJson(['reactions' => [['key' => 'fire', 'count' => 1]], 'my_reaction' => 'fire', 'total' => 1]);
        $this->assertDatabaseCount('chapter_reactions', 1);

        // Tekan lagi = cabut.
        $this->actingAs($user)->postJson($this->url(), ['reaction' => 'fire'])
            ->assertOk()
            ->assertExactJson(['reactions' => [], 'my_reaction' => null, 'total' => 0]);
        $this->assertDatabaseCount('chapter_reactions', 0);
    }

    public function test_counts_are_separate_per_key_ordered_and_my_reaction_is_per_viewer(): void
    {
        [$a, $b, $c] = [User::factory()->create(), User::factory()->create(), User::factory()->create()];

        $this->actingAs($a)->postJson($this->url(), ['reaction' => 'fire'])->assertOk();
        $this->actingAs($b)->postJson($this->url(), ['reaction' => 'like'])->assertOk();
        $res = $this->actingAs($c)->postJson($this->url(), ['reaction' => 'fire'])->assertOk();

        // Urutan mengikuti ChapterReactionService::KEYS (like sebelum fire), bukan urutan masuk.
        $res->assertExactJson([
            'reactions' => [['key' => 'like', 'count' => 1], ['key' => 'fire', 'count' => 2]],
            'my_reaction' => 'fire',
            'total' => 3,
        ]);

        $this->actingAs($b)->getJson($this->url())->assertJsonPath('my_reaction', 'like');
    }

    public function test_every_listed_key_works_and_unknown_keys_are_rejected(): void
    {
        $user = User::factory()->create();

        foreach (['like', 'love', 'laugh', 'wow', 'sad', 'fire'] as $key) {
            $this->actingAs($user)->postJson($this->url(), ['reaction' => $key])->assertOk()->assertJsonPath('my_reaction', $key);
        }

        foreach (['👍', 'LIKE', 'angry', '', 'like; drop table'] as $bad) {
            $this->actingAs($user)->postJson($this->url(), ['reaction' => $bad])
                ->assertUnprocessable()->assertJsonValidationErrors(['reaction']);
        }
        $this->actingAs($user)->postJson($this->url(), [])->assertUnprocessable();
    }

    public function test_reactions_are_per_chapter(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->postJson($this->url(1), ['reaction' => 'love'])->assertOk();

        $this->actingAs($user)->getJson($this->url(2))->assertOk()->assertJsonPath('total', 0);
        $this->actingAs($user)->postJson($this->url(2), ['reaction' => 'wow'])->assertOk();

        $this->assertDatabaseCount('chapter_reactions', 2);
        $this->actingAs($user)->getJson($this->url(1))->assertJsonPath('my_reaction', 'love');
    }

    public function test_unknown_comic_or_chapter_is_404(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson($this->url(1, 'no-such-comic'))->assertNotFound();
        $this->actingAs($user)->getJson($this->url(99))->assertNotFound();
        $this->actingAs($user)->postJson($this->url(1, 'no-such-comic'), ['reaction' => 'like'])->assertNotFound();
        $this->actingAs($user)->postJson($this->url(99), ['reaction' => 'like'])->assertNotFound();
        $this->assertDatabaseCount('chapter_reactions', 0);
    }

    public function test_chapter_reactions_never_touch_the_discuss_reaction_table(): void
    {
        $this->actingAs(User::factory()->create())->postJson($this->url(), ['reaction' => 'like'])->assertOk();

        $this->assertDatabaseCount('discuss_reactions', 0);
        $this->assertDatabaseCount('chapter_reactions', 1);
    }
}
