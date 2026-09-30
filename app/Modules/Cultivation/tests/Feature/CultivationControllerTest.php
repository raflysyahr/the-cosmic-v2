<?php

namespace App\Modules\Cultivation\tests\Feature;

use App\Modules\Auth\Models\User;
use App\Modules\Cultivation\Models\CultivationEra;
use App\Modules\Cultivation\Models\CultivationRealm;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CultivationControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $era = CultivationEra::create([
            'name' => 'Mortal Era', 'resource_name' => 'Essence',
            'resource_slug' => 'essence', 'sort_order' => 1,
        ]);

        CultivationRealm::create([
            'era_id' => $era->id, 'name' => 'Awakened', 'full_name' => 'Awakening Realm',
            'level_start' => 1, 'level_end' => 10,
            'stage_required' => 100, 'realm_total_required' => 1000, 'sort_order' => 1,
        ]);
    }

    public function test_guest_cannot_access_cultivation_status(): void
    {
        $response = $this->getJson('/api/cultivation');

        $response->assertStatus(401);
    }

    public function test_authenticated_user_can_get_cultivation_status(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/cultivation');

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data' => [
                'level' => 1,
                'stage' => 1,
                'progress' => 0,
                'era' => ['resource_name' => 'Essence'],
            ],
        ]);
    }

    public function test_complete_chapter_rejects_duration_under_six_seconds(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/cultivation/chapter-complete', [
            'slug' => 'series-a',
            'chapter_index' => 1,
            'started_at' => now()->subSeconds(2)->toIso8601String(),
        ]);

        $response->assertStatus(422);
    }

    public function test_complete_chapter_grants_xp_after_six_seconds(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/cultivation/chapter-complete', [
            'slug' => 'series-a',
            'chapter_index' => 1,
            'started_at' => now()->subSeconds(10)->toIso8601String(),
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'cultivation_gain' => [
                'resource_name' => 'Essence',
            ],
        ]);
    }

    public function test_complete_chapter_does_not_double_grant_same_chapter(): void
    {
        $user = User::factory()->create();
        $payload = [
            'slug' => 'series-a',
            'chapter_index' => 1,
            'started_at' => now()->subSeconds(10)->toIso8601String(),
        ];

        $this->actingAs($user)->postJson('/api/cultivation/chapter-complete', $payload);
        $second = $this->actingAs($user)->postJson('/api/cultivation/chapter-complete', $payload);

        $second->assertStatus(200);
        $second->assertJson(['success' => true, 'cultivation_gain' => null]);
    }

    public function test_complete_chapter_requires_authentication(): void
    {
        $response = $this->postJson('/api/cultivation/chapter-complete', [
            'slug' => 'series-a',
            'chapter_index' => 1,
            'started_at' => now()->subSeconds(10)->toIso8601String(),
        ]);

        $response->assertStatus(401);
    }

    public function test_guide_is_accessible_without_authentication(): void
    {
        // Beda dari /api/cultivation (status personal) — /guide adalah
        // data referensi publik, tidak boleh butuh login.
        $response = $this->getJson('/api/cultivation/guide');

        $response->assertStatus(200);
    }

    public function test_guide_returns_eras_with_nested_realms(): void
    {
        $response = $this->getJson('/api/cultivation/guide');

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data' => [
                [
                    'name' => 'Mortal Era',
                    'resource_name' => 'Essence',
                    'realms' => [
                        ['name' => 'Awakened', 'slug' => 'awakened'],
                    ],
                ],
            ],
        ]);
    }
}
