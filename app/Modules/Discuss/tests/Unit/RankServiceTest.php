<?php

namespace App\Modules\Discuss\tests\Unit;

use App\Modules\Auth\Models\User;
use App\Modules\Discuss\Models\Member;
use App\Modules\Discuss\Models\Rank;
use App\Modules\Discuss\Models\Room;
use App\Modules\Discuss\Services\RankService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RankServiceTest extends TestCase
{
    use RefreshDatabase;

    private RankService $rankService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rankService = app(RankService::class);

        Rank::create(['name' => 'Newcomer', 'label_color' => '#666', 'min_xp' => 0, 'order' => 1]);
        Rank::create(['name' => 'Regular', 'label_color' => '#0f0', 'min_xp' => 100, 'order' => 2]);
        Rank::create(['name' => 'Veteran', 'label_color' => '#00f', 'min_xp' => 500, 'order' => 3]);
    }

    public function test_award_xp_increases_points(): void
    {
        $member = $this->createMember();

        $this->rankService->awardXp($member, 50);

        $this->assertEquals(50, $member->fresh()->xp_points);
    }

    public function test_check_promotion_promotes_when_xp_sufficient(): void
    {
        $member = $this->createMember();
        $member->update(['xp_points' => 150]);

        $this->rankService->checkPromotion($member->fresh());

        $this->assertNotNull($member->fresh()->rank_id);

        $rank = Rank::find($member->fresh()->rank_id);
        $this->assertEquals('Regular', $rank->name);
    }

    public function test_check_promotion_does_not_demote(): void
    {
        $regularRank = Rank::where('name', 'Regular')->first();

        $member = $this->createMember();
        $member->update([
            'xp_points' => 150,
            'rank_id' => $regularRank->id,
        ]);

        $this->rankService->checkPromotion($member->fresh());

        $this->assertEquals($regularRank->id, $member->fresh()->rank_id);
    }

    public function test_check_promotion_assigns_newcomer_at_zero_xp(): void
    {
        $member = $this->createMember();
        $member->update(['xp_points' => 0]);

        $this->rankService->checkPromotion($member->fresh());

        $newcomer = Rank::where('name', 'Newcomer')->first();
        $this->assertEquals($newcomer->id, $member->fresh()->rank_id);
    }

    private function createMember(): Member
    {
        $user = User::factory()->create();
        $room = Room::factory()->create();

        return Member::create([
            'room_id' => $room->id,
            'user_id' => $user->id,
            'role' => 'member',
            'xp_points' => 0,
            'is_banned' => false,
            'joined_at' => now(),
            'last_read_at' => now(),
        ]);
    }
}
