<?php

namespace Tests\Feature;

use App\Models\ReadingHistory;
use App\Modules\Auth\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReadingHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_mark_chapter_as_read(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/reading-history', [
            'slug' => 'my-comic',
            'title' => 'My Comic',
            'chapter_index' => 3,
            'chapter_title' => 'Chapter 3',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('reading_histories', [
            'user_id' => $user->id,
            'slug' => 'my-comic',
        ]);

        $entry = ReadingHistory::where('user_id', $user->id)
            ->where('slug', 'my-comic')
            ->first();

        $this->assertEquals([3], $entry->read_chapters);
        $this->assertEquals(3, $entry->last_chapter_index);
    }

    public function test_marking_same_chapter_read_twice_is_idempotent(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/reading-history', [
            'slug' => 'my-comic', 'title' => 'My Comic', 'chapter_index' => 1,
        ]);
        $this->actingAs($user)->postJson('/api/reading-history', [
            'slug' => 'my-comic', 'title' => 'My Comic', 'chapter_index' => 1,
        ]);

        // Only one row per user+slug (unique constraint)
        $this->assertEquals(
            1,
            ReadingHistory::where('user_id', $user->id)->where('slug', 'my-comic')->count(),
        );

        // Chapter appears only once in the array
        $entry = ReadingHistory::where('user_id', $user->id)
            ->where('slug', 'my-comic')
            ->first();

        $this->assertEquals([1], $entry->read_chapters);
    }

    public function test_can_list_read_chapters_for_a_series(): void
    {
        $user = User::factory()->create();

        // Use markRead to simulate real behavior (one row per series)
        app(\App\Services\ReadingHistoryService::class)->markRead(
            $user->id, 'my-comic', 'My Comic', null, 1,
        );
        app(\App\Services\ReadingHistoryService::class)->markRead(
            $user->id, 'my-comic', 'My Comic', null, 2,
        );
        app(\App\Services\ReadingHistoryService::class)->markRead(
            $user->id, 'other-comic', 'Other', null, 5,
        );

        $response = $this->actingAs($user)->getJson('/api/reading-history/my-comic');

        $response->assertStatus(200);
        $response->assertJson(['read_chapters' => [1, 2]]);
    }

    public function test_guest_cannot_access_reading_history(): void
    {
        $response = $this->getJson('/api/reading-history/my-comic');

        $response->assertStatus(401);
    }
}
