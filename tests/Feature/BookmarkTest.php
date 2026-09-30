<?php

namespace Tests\Feature;

use App\Models\Bookmark;
use App\Modules\Auth\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookmarkTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_add_bookmark(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/bookmarks', [
            'slug' => 'my-comic',
            'title' => 'My Comic',
            'cover_image' => 'https://example.com/cover.jpg',
            'format' => 'manga',
        ]);

        $response->assertStatus(201);
        $response->assertJson(['data' => [
            'slug' => 'my-comic',
            'title' => 'My Comic',
        ]]);
        $this->assertDatabaseHas('bookmarks', [
            'user_id' => $user->id,
            'slug' => 'my-comic',
            'title' => 'My Comic',
        ]);
    }

    public function test_duplicate_bookmark_is_idempotent(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/bookmarks', [
            'slug' => 'my-comic', 'title' => 'My Comic',
        ]);
        $this->actingAs($user)->postJson('/api/bookmarks', [
            'slug' => 'my-comic', 'title' => 'My Comic',
        ]);

        $this->assertEquals(
            1,
            Bookmark::where('user_id', $user->id)->where('slug', 'my-comic')->count(),
        );
    }

    public function test_can_list_bookmarks(): void
    {
        $user = User::factory()->create();
        Bookmark::create(['user_id' => $user->id, 'slug' => 'comic-a', 'title' => 'Comic A']);
        Bookmark::create(['user_id' => $user->id, 'slug' => 'comic-b', 'title' => 'Comic B']);

        $response = $this->actingAs($user)->getJson('/api/bookmarks');

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'data');
    }

    public function test_can_remove_bookmark(): void
    {
        $user = User::factory()->create();
        Bookmark::create(['user_id' => $user->id, 'slug' => 'my-comic', 'title' => 'My Comic']);

        $response = $this->actingAs($user)->deleteJson('/api/bookmarks/my-comic');

        $response->assertStatus(200);
        $this->assertDatabaseMissing('bookmarks', [
            'user_id' => $user->id,
            'slug' => 'my-comic',
        ]);
    }

    public function test_guest_cannot_access_bookmarks(): void
    {
        $response = $this->getJson('/api/bookmarks');
        $response->assertStatus(401);
    }
}
