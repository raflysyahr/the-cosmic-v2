<?php

namespace App\Modules\Discuss\tests\Unit;

use App\Modules\Auth\Models\User;
use App\Modules\Discuss\Models\Member;
use App\Modules\Discuss\Models\Rank;
use App\Modules\Discuss\Models\Room;
use App\Modules\Discuss\Services\MemberService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MemberServiceTest extends TestCase
{
    use RefreshDatabase;

    private MemberService $memberService;
    private Room $room;

    protected function setUp(): void
    {
        parent::setUp();

        $this->memberService = app(MemberService::class);
        $this->room = Room::factory()->create();
    }

    private function addMember(User $user, string $role = 'member'): Member
    {
        return Member::create([
            'room_id' => $this->room->id,
            'user_id' => $user->id,
            'role' => $role,
            'xp_points' => 0,
            'is_banned' => false,
            'joined_at' => now(),
            'last_read_at' => now(),
        ]);
    }

    public function test_moderator_can_kick_member(): void
    {
        $moderator = User::factory()->create();
        $target = User::factory()->create();
        $this->addMember($moderator, 'moderator');
        $targetMember = $this->addMember($target);

        $this->memberService->kick($targetMember, $moderator->id);

        $this->assertNull(Member::find($targetMember->id));
    }

    public function test_regular_member_cannot_kick(): void
    {
        $regular = User::factory()->create();
        $target = User::factory()->create();
        $this->addMember($regular);
        $targetMember = $this->addMember($target);

        $this->expectException(ValidationException::class);

        $this->memberService->kick($targetMember, $regular->id);

        $this->assertNotNull(Member::find($targetMember->id));
    }

    public function test_non_member_cannot_kick(): void
    {
        $outsider = User::factory()->create();
        $target = User::factory()->create();
        $targetMember = $this->addMember($target);

        $this->expectException(ValidationException::class);

        $this->memberService->kick($targetMember, $outsider->id);
    }

    public function test_cannot_kick_self(): void
    {
        $moderator = User::factory()->create();
        $moderatorMember = $this->addMember($moderator, 'moderator');

        $this->expectException(ValidationException::class);

        $this->memberService->kick($moderatorMember, $moderator->id);
    }

    public function test_moderator_cannot_kick_another_moderator(): void
    {
        $moderatorA = User::factory()->create();
        $moderatorB = User::factory()->create();
        $this->addMember($moderatorA, 'moderator');
        $memberB = $this->addMember($moderatorB, 'moderator');

        $this->expectException(ValidationException::class);

        $this->memberService->kick($memberB, $moderatorA->id);
    }

    public function test_admin_can_kick_moderator(): void
    {
        $admin = User::factory()->create();
        $moderator = User::factory()->create();
        $this->addMember($admin, 'admin');
        $moderatorMember = $this->addMember($moderator, 'moderator');

        $this->memberService->kick($moderatorMember, $admin->id);

        $this->assertNull(Member::find($moderatorMember->id));
    }

    public function test_moderator_can_mute_member(): void
    {
        $moderator = User::factory()->create();
        $target = User::factory()->create();
        $this->addMember($moderator, 'moderator');
        $targetMember = $this->addMember($target);

        $this->memberService->mute($targetMember, 30, $moderator->id);

        $this->assertNotNull($targetMember->fresh()->muted_until);
    }

    public function test_regular_member_cannot_mute(): void
    {
        $regular = User::factory()->create();
        $target = User::factory()->create();
        $this->addMember($regular);
        $targetMember = $this->addMember($target);

        $this->expectException(ValidationException::class);

        $this->memberService->mute($targetMember, 30, $regular->id);

        $this->assertNull($targetMember->fresh()->muted_until);
    }

    public function test_moderator_can_ban_member(): void
    {
        $moderator = User::factory()->create();
        $target = User::factory()->create();
        $this->addMember($moderator, 'moderator');
        $targetMember = $this->addMember($target);

        $this->memberService->ban($targetMember, $moderator->id);

        $this->assertTrue($targetMember->fresh()->is_banned);
    }

    public function test_regular_member_cannot_ban(): void
    {
        $regular = User::factory()->create();
        $target = User::factory()->create();
        $this->addMember($regular);
        $targetMember = $this->addMember($target);

        $this->expectException(ValidationException::class);

        $this->memberService->ban($targetMember, $regular->id);

        $this->assertFalse($targetMember->fresh()->is_banned);
    }

    public function test_list_for_room_resolves_rank_name_and_color(): void
    {
        $rank = Rank::create([
            'name' => 'Veteran',
            'label_color' => '#00ff00',
            'min_xp' => 500,
            'order' => 3,
        ]);

        $user = User::factory()->create();
        $member = $this->addMember($user);
        $member->update(['rank_id' => $rank->id]);

        $result = $this->memberService->listForRoom($this->room->id);
        $entry = $result->firstWhere('userId', $user->id);

        $this->assertNotNull($entry['rank']);
        $this->assertEquals('Veteran', $entry['rank']['name']);
        $this->assertEquals('#00ff00', $entry['rank']['color']);
    }

    public function test_list_for_room_returns_null_rank_when_member_has_none(): void
    {
        $user = User::factory()->create();
        $this->addMember($user);

        $result = $this->memberService->listForRoom($this->room->id);
        $entry = $result->firstWhere('userId', $user->id);

        $this->assertNull($entry['rank']);
    }
}
