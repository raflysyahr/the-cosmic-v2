<?php

namespace Tests\Feature;

use App\Models\Series;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeriesApiLimitsTest extends TestCase
{
    use RefreshDatabase;

    private function series(string $title, ?string $slug = null): Series
    {
        return Series::create([
            'slug' => $slug ?? str($title)->slug()->toString() . '-' . uniqid(),
            'title' => $title,
            'status' => 'ongoing',
            'type' => 'manga',
        ]);
    }

    public function test_invalid_take_and_page_do_not_error(): void
    {
        $this->series('Alpha');

        foreach (['take=0', 'take=-5', 'take=abc', 'page=0', 'page=-3', 'take=0&page=0'] as $query) {
            $this->getJson("/api/v1/series?{$query}")->assertOk()->assertJsonPath('success', true);
        }
    }

    public function test_take_is_capped_at_200(): void
    {
        foreach (range(1, 205) as $i) {
            $this->series("Judul {$i}");
        }

        $response = $this->getJson('/api/v1/series?take=100000')->assertOk();

        $this->assertCount(200, $response->json('data'));
        $this->assertSame(205, $response->json('meta.total'));
    }

    public function test_the_genre_filter_request_of_200_still_works(): void
    {
        foreach (range(1, 205) as $i) {
            $this->series("Judul {$i}");
        }

        $this->assertCount(200, $this->getJson('/api/v1/series?take=200&page=1')->json('data'));
    }

    public function test_search_treats_percent_and_underscore_literally(): void
    {
        $this->series('Alpha');
        $this->series('100% Wolf');
        $this->series('a_b');
        $this->series('axb');

        $percent = $this->getJson('/api/v1/series/search?q=' . urlencode('%'))->assertOk();
        $this->assertSame(['100% Wolf'], collect($percent->json('data'))->pluck('title')->all());

        $underscore = $this->getJson('/api/v1/series/search?q=' . urlencode('a_b'))->assertOk();
        $this->assertSame(['a_b'], collect($underscore->json('data'))->pluck('title')->all());
    }

    public function test_search_still_matches_normal_queries_and_handles_bad_input(): void
    {
        $this->series('Solo Leveling');

        $this->getJson('/api/v1/series/search?q=solo')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/series/search?q=' . str_repeat('x', 500))->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/series/search?q=solo&take=0')->assertOk();
        $this->getJson('/api/v1/series/search')->assertOk()->assertJsonCount(0, 'data');
    }
}
