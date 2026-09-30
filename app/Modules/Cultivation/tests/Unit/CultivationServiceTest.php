<?php

namespace App\Modules\Cultivation\tests\Unit;

use App\Modules\Auth\Models\User;
use App\Modules\Cultivation\Models\CultivationEra;
use App\Modules\Cultivation\Models\CultivationRealm;
use App\Modules\Cultivation\Models\UserCultivation;
use App\Modules\Cultivation\Services\CultivationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CultivationServiceTest extends TestCase
{
    use RefreshDatabase;

    private CultivationService $cultivationService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cultivationService = app(CultivationService::class);

        $era1 = CultivationEra::create([
            'name' => 'Mortal Era', 'resource_name' => 'Essence',
            'resource_slug' => 'essence', 'sort_order' => 1,
        ]);
        $era2 = CultivationEra::create([
            'name' => 'Astral Era', 'resource_name' => 'Astrum',
            'resource_slug' => 'astrum', 'sort_order' => 2,
        ]);

        // Realm 1: stage_required=100 (era 1)
        CultivationRealm::create([
            'era_id' => $era1->id, 'name' => 'Awakened', 'full_name' => 'Awakening Realm',
            'level_start' => 1, 'level_end' => 10,
            'stage_required' => 100, 'realm_total_required' => 1000, 'sort_order' => 1,
        ]);
        // Realm 2: stage_required=200 (era 1 juga, biar test pindah realm dalam 1 era dulu)
        CultivationRealm::create([
            'era_id' => $era1->id, 'name' => 'Gatherer', 'full_name' => 'Energy Gathering Realm',
            'level_start' => 11, 'level_end' => 20,
            'stage_required' => 200, 'realm_total_required' => 2000, 'sort_order' => 2,
        ]);
        // Realm 3: realm TERAKHIR (buat test capping max level) — era 2
        CultivationRealm::create([
            'era_id' => $era2->id, 'name' => 'Starforged', 'full_name' => 'Starforged Realm',
            'level_start' => 21, 'level_end' => 30,
            'stage_required' => 400, 'realm_total_required' => 4000, 'sort_order' => 3,
        ]);
    }

    public function test_new_user_starts_at_first_realm_stage_one(): void
    {
        $user = User::factory()->create();

        $cultivation = $this->cultivationService->getOrCreateForUser($user);

        $firstRealm = CultivationRealm::where('sort_order', 1)->first();
        $this->assertEquals($firstRealm->id, $cultivation->realm_id);
        $this->assertEquals(1, $cultivation->stage);
        $this->assertEquals(0, $cultivation->progress);
    }

    public function test_add_progress_below_threshold_does_not_breakthrough(): void
    {
        $user = User::factory()->create();

        $result = $this->cultivationService->addProgress($user, 'chapter_read', 'series-a:1', 50);

        $this->assertNotNull($result);
        $this->assertEquals(1, $result['stage']);
        $this->assertEquals(50, $result['progress']);
        $this->assertEmpty($result['breakthroughs']);
    }

    public function test_add_progress_triggers_single_stage_breakthrough(): void
    {
        $user = User::factory()->create();

        $result = $this->cultivationService->addProgress($user, 'chapter_read', 'series-a:1', 60);
        $result = $this->cultivationService->addProgress($user, 'chapter_read', 'series-a:2', 60);

        // 60 + 60 = 120, stage_required realm 1 = 100 -> breakthrough sekali, sisa progress 20
        $this->assertEquals(2, $result['stage']);
        $this->assertEquals(20, $result['progress']);
        $this->assertCount(1, $result['breakthroughs']);
    }

    public function test_add_progress_can_breakthrough_multiple_stages_at_once(): void
    {
        $user = User::factory()->create();

        // Amount besar sekaligus (mis. reward mission gede) harus bisa
        // lompat beberapa stage dalam satu panggilan.
        $result = $this->cultivationService->addProgress($user, 'mission', 'mission:1', 250);

        // 250 / 100 = breakthrough 2x, sisa progress 50
        $this->assertEquals(3, $result['stage']);
        $this->assertEquals(50, $result['progress']);
        $this->assertCount(2, $result['breakthroughs']);
    }

    public function test_add_progress_moves_to_next_realm_after_stage_ten(): void
    {
        $user = User::factory()->create();
        $realm1 = CultivationRealm::where('sort_order', 1)->first();

        UserCultivation::create([
            'user_id' => $user->id, 'realm_id' => $realm1->id,
            'stage' => 10, 'progress' => 90,
        ]);

        $result = $this->cultivationService->addProgress($user, 'chapter_read', 'series-a:1', 20);

        $realm2 = CultivationRealm::where('sort_order', 2)->first();
        $this->assertEquals($realm2->id, $result['realm']->id);
        $this->assertEquals(1, $result['stage']);
        $this->assertEquals(10, $result['progress']); // 90+20-100=10
    }

    public function test_add_progress_caps_at_max_realm_and_stage(): void
    {
        $user = User::factory()->create();
        $lastRealm = CultivationRealm::where('sort_order', 3)->first();

        UserCultivation::create([
            'user_id' => $user->id, 'realm_id' => $lastRealm->id,
            'stage' => 10, 'progress' => 0,
        ]);

        $result = $this->cultivationService->addProgress($user, 'chapter_read', 'series-a:1', 999999);

        $this->assertEquals($lastRealm->id, $result['realm']->id);
        $this->assertEquals(10, $result['stage']);
        $this->assertEquals(0, $result['progress']);
        $this->assertEmpty($result['breakthroughs']);
    }

    public function test_add_progress_is_deduped_by_source_and_reference(): void
    {
        $user = User::factory()->create();

        $first = $this->cultivationService->addProgress($user, 'chapter_read', 'series-a:1', 50);
        $second = $this->cultivationService->addProgress($user, 'chapter_read', 'series-a:1', 50);

        $this->assertNotNull($first);
        $this->assertNull($second, 'Referensi yang sama harus di-skip, tidak boleh kasih XP dobel.');

        $cultivation = UserCultivation::where('user_id', $user->id)->first();
        $this->assertEquals(50, $cultivation->progress, 'Progress tidak boleh nambah dari klaim duplikat.');
    }

    public function test_add_progress_allows_same_source_different_reference(): void
    {
        $user = User::factory()->create();

        $this->cultivationService->addProgress($user, 'chapter_read', 'series-a:1', 50);
        $this->cultivationService->addProgress($user, 'chapter_read', 'series-a:2', 50);

        $cultivation = UserCultivation::where('user_id', $user->id)->first();
        $this->assertEquals(100, $cultivation->progress);
    }

    public function test_add_progress_ignores_zero_or_negative_amount(): void
    {
        $user = User::factory()->create();

        $this->assertNull($this->cultivationService->addProgress($user, 'chapter_read', 'series-a:1', 0));
        $this->assertNull($this->cultivationService->addProgress($user, 'chapter_read', 'series-a:2', -10));
    }

    public function test_get_status_returns_correct_level_and_resource(): void
    {
        $user = User::factory()->create();
        $this->cultivationService->addProgress($user, 'chapter_read', 'series-a:1', 60);

        $status = $this->cultivationService->getStatus($user);

        $this->assertEquals(1, $status['level']); // realm1.level_start(1) + stage(1) - 1
        $this->assertEquals('Essence', $status['era']['resource_name']);
        $this->assertEquals(60, $status['progress']);
        $this->assertEquals(60.0, $status['progress_percent']);
    }

    public function test_get_realm_badge_data_returns_batched_results(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $this->cultivationService->addProgress($userA, 'chapter_read', 'series-a:1', 10);
        $this->cultivationService->addProgress($userB, 'chapter_read', 'series-a:1', 10);

        $badges = $this->cultivationService->getRealmBadgeData([$userA->id, $userB->id]);

        $this->assertArrayHasKey($userA->id, $badges);
        $this->assertArrayHasKey($userB->id, $badges);
        $this->assertEquals('Awakened', $badges[$userA->id]['realm_name']);
        $this->assertEquals('awakened', $badges[$userA->id]['realm_slug']);
    }

    public function test_get_realm_badge_data_returns_empty_for_no_users(): void
    {
        $this->assertEquals([], $this->cultivationService->getRealmBadgeData([]));
    }

    public function test_realm_badge_slug_for_two_word_realm_names_has_no_dash(): void
    {
        // Regression test: Str::slug('Star Lord') = 'star-lord' (dengan
        // dash), tapi nama file ikon asli di storage/app/private/realm/
        // adalah 'starlord.png' (TANPA dash) — lihat realmIconSlug() di
        // CultivationService. Kalau ini pakai Str::slug() biasa, badge
        // untuk realm 2-kata (mis. "Star Lord", "World Lord") akan 404.
        $era = CultivationEra::where('sort_order', 1)->first();
        CultivationRealm::create([
            'era_id' => $era->id, 'name' => 'Star Lord', 'full_name' => 'Star Lord Realm',
            'level_start' => 61, 'level_end' => 70,
            'stage_required' => 6400, 'realm_total_required' => 64000, 'sort_order' => 7,
        ]);

        $user = User::factory()->create();
        $starLordRealm = CultivationRealm::where('name', 'Star Lord')->first();
        UserCultivation::create([
            'user_id' => $user->id, 'realm_id' => $starLordRealm->id,
            'stage' => 1, 'progress' => 0,
        ]);

        $badges = $this->cultivationService->getRealmBadgeData([$user->id]);

        $this->assertEquals('starlord', $badges[$user->id]['realm_slug']);
    }

    public function test_get_guide_data_groups_realms_under_correct_era_in_order(): void
    {
        // setUp() sudah bikin 2 era (Mortal Era, Astral Era) dan 3 realm
        // (Awakened+Gatherer di era1, Starforged di era2).
        $guide = $this->cultivationService->getGuideData();

        $this->assertCount(2, $guide);

        $era1 = $guide[0];
        $this->assertEquals('Mortal Era', $era1['name']);
        $this->assertCount(2, $era1['realms']);
        $this->assertEquals('Awakened', $era1['realms'][0]['name']);
        $this->assertEquals('Gatherer', $era1['realms'][1]['name']);

        $era2 = $guide[1];
        $this->assertEquals('Astral Era', $era2['name']);
        $this->assertCount(1, $era2['realms']);
        $this->assertEquals('Starforged', $era2['realms'][0]['name']);
    }

    public function test_get_guide_data_includes_icon_slug_without_dash(): void
    {
        $era = CultivationEra::where('sort_order', 1)->first();
        CultivationRealm::create([
            'era_id' => $era->id, 'name' => 'World Lord', 'full_name' => 'World Lord Realm',
            'level_start' => 71, 'level_end' => 80,
            'stage_required' => 12800, 'realm_total_required' => 128000, 'sort_order' => 8,
        ]);

        $guide = $this->cultivationService->getGuideData();
        $era1Realms = collect($guide[0]['realms']);
        $worldLord = $era1Realms->firstWhere('name', 'World Lord');

        $this->assertEquals('worldlord', $worldLord['slug']);
    }

    public function test_get_guide_data_returns_empty_realms_array_for_era_without_realms(): void
    {
        CultivationEra::create([
            'name' => 'Empty Era', 'resource_name' => 'Nothing',
            'resource_slug' => 'nothing', 'sort_order' => 99,
        ]);

        $guide = $this->cultivationService->getGuideData();
        $emptyEra = collect($guide)->firstWhere('name', 'Empty Era');

        $this->assertNotNull($emptyEra);
        $this->assertEquals([], $emptyEra['realms']);
    }
}
