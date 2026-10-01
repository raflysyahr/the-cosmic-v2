<?php

namespace App\Modules\Discuss\tests\Feature;

use App\Modules\Auth\Models\User;
use App\Modules\Discuss\Events\ContributionAwarded;
use App\Modules\Discuss\Models\CpLog;
use App\Modules\Discuss\Models\CpSetting;
use App\Modules\Discuss\Models\Member;
use App\Modules\Discuss\Models\Message;
use App\Modules\Discuss\Models\Report;
use App\Modules\Discuss\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ReportControllerTest extends TestCase
{
    use RefreshDatabase;

    private Room $room;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake([ContributionAwarded::class]);
        $this->room = Room::factory()->create();
    }

    private function member(string $role = 'member', int $xp = 0): User
    {
        $user = User::factory()->create();
        Member::create([
            'room_id' => $this->room->id, 'user_id' => $user->id, 'role' => $role, 'xp_points' => $xp,
            'is_banned' => false, 'joined_at' => now()->subDays(2), 'last_read_at' => now(),
        ]);

        return $user;
    }

    private function message(User $author): Message
    {
        return Message::factory()->create(['room_id' => $this->room->id, 'user_id' => $author->id]);
    }

    private function report(User $reporter, Message $message, string $reason = 'spam')
    {
        return $this->actingAs($reporter)->postJson(
            "/api/rooms/{$this->room->slug}/messages/{$message->id}/report",
            ['reason' => $reason, 'note' => 'Looks off'],
        );
    }

    private function resolve(User $actor, string $reportId, array $payload)
    {
        return $this->actingAs($actor)->postJson("/api/rooms/{$this->room->slug}/reports/{$reportId}/resolve", $payload);
    }

    private function xp(User $user): int
    {
        return (int) Member::where('room_id', $this->room->id)->where('user_id', $user->id)->value('xp_points');
    }

    public function test_member_can_report_a_message_and_it_starts_pending(): void
    {
        $author = $this->member();
        $reporter = $this->member();
        $message = $this->message($author);

        $this->report($reporter, $message, 'harassment')
            ->assertStatus(201)
            ->assertJsonPath('report.status', 'pending')
            ->assertJsonPath('report.reason', 'harassment')
            ->assertJsonPath('report.message.id', $message->id);

        $this->assertDatabaseHas('discuss_reports', [
            'message_id' => $message->id, 'reporter_id' => $reporter->id,
            'message_author_id' => $author->id, 'status' => 'pending',
        ]);
        // Melapor saja tidak memberi CP.
        $this->assertSame(0, CpLog::where('source', 'report_valid')->count());
    }

    public function test_report_rules_are_enforced(): void
    {
        $author = $this->member();
        $reporter = $this->member();
        $message = $this->message($author);

        $this->report($author, $message)->assertStatus(422)->assertJsonPath('errors.report.0', 'You cannot report your own message.');

        $this->actingAs($reporter)
            ->postJson("/api/rooms/{$this->room->slug}/messages/{$message->id}/report", ['reason' => 'nonsense'])
            ->assertStatus(422)->assertJsonValidationErrors(['reason']);

        $this->report(User::factory()->create(), $message)->assertStatus(422);

        $this->report($reporter, $message)->assertStatus(201);
        $this->report($reporter, $message)->assertStatus(422)->assertJsonPath('errors.report.0', 'You already reported this message.');

        $this->assertSame(1, Report::count());
    }

    public function test_daily_report_limit_is_admin_configurable(): void
    {
        CpSetting::create(['key' => 'report_daily_limit', 'value' => ['v' => 1]]);
        $author = $this->member();
        $reporter = $this->member();

        $this->report($reporter, $this->message($author))->assertStatus(201);
        $this->report($reporter, $this->message($author))->assertStatus(422);
    }

    public function test_only_moderators_can_list_the_queue(): void
    {
        $author = $this->member();
        $reporter = $this->member();
        $moderator = $this->member('moderator');
        $message = $this->message($author);
        $this->report($reporter, $message)->assertStatus(201);

        $this->actingAs($reporter)->getJson("/api/rooms/{$this->room->slug}/reports")->assertStatus(422);

        $this->actingAs($moderator)->getJson("/api/rooms/{$this->room->slug}/reports")
            ->assertOk()
            ->assertJsonCount(1, 'reports')
            ->assertJsonPath('reports.0.author.id', $author->id)
            ->assertJsonPath('reports.0.reporter.id', $reporter->id)
            ->assertJsonPath('reports.0.message.is_deleted', false);

        $this->actingAs($moderator)->getJson("/api/rooms/{$this->room->slug}/reports?status=valid")
            ->assertOk()->assertJsonCount(0, 'reports');
    }

    public function test_valid_report_pays_reporter_penalizes_author_and_removes_the_message(): void
    {
        $author = $this->member('member', 30);
        $reporter = $this->member();
        $moderator = $this->member('moderator');
        $message = $this->message($author);
        $reportId = $this->report($reporter, $message)->json('report.id');

        $this->resolve($moderator, $reportId, ['outcome' => 'valid', 'penalty' => 'spam', 'delete_message' => true])
            ->assertOk()
            ->assertJsonPath('report.status', 'valid')
            ->assertJsonPath('report.penalty', 'spam')
            ->assertJsonPath('report.message.is_deleted', true);

        $this->assertSame(5, $this->xp($reporter));
        $this->assertDatabaseHas('discuss_cp_logs', ['user_id' => $reporter->id, 'source' => 'report_valid', 'amount' => 5, 'reference' => $reportId]);
        $this->assertSame(20, $this->xp($author));
        $this->assertDatabaseHas('discuss_cp_logs', ['user_id' => $author->id, 'source' => 'penalty', 'amount' => -10, 'reference' => "report:{$reportId}"]);
        $this->assertTrue((bool) $message->fresh()->is_deleted);
        // Poin pelapor tidak ikut tercabut saat pesan yang dilaporkan dihapus.
        $this->assertSame(5, $this->xp($reporter));
    }

    public function test_manipulation_penalty_uses_its_own_amount_and_floors_at_zero(): void
    {
        $author = $this->member('member', 30);
        $reporter = $this->member();
        $moderator = $this->member('moderator');
        $reportId = $this->report($reporter, $this->message($author), 'manipulation')->json('report.id');

        $this->resolve($moderator, $reportId, ['outcome' => 'valid', 'penalty' => 'manipulation'])->assertOk();

        $this->assertSame(0, $this->xp($author));
        $this->assertDatabaseHas('discuss_cp_logs', ['user_id' => $author->id, 'source' => 'penalty', 'amount' => -50]);
    }

    public function test_valid_report_without_penalty_only_rewards_the_reporter(): void
    {
        $author = $this->member('member', 30);
        $reporter = $this->member();
        $moderator = $this->member('moderator');
        $message = $this->message($author);
        $reportId = $this->report($reporter, $message)->json('report.id');

        $this->resolve($moderator, $reportId, ['outcome' => 'valid'])->assertOk()->assertJsonPath('report.penalty', 'none');

        $this->assertSame(5, $this->xp($reporter));
        $this->assertSame(30, $this->xp($author));
        $this->assertFalse((bool) $message->fresh()->is_deleted);
    }

    public function test_dismissed_report_changes_nothing_even_if_a_penalty_is_sent(): void
    {
        $author = $this->member('member', 30);
        $reporter = $this->member();
        $moderator = $this->member('moderator');
        $message = $this->message($author);
        $reportId = $this->report($reporter, $message)->json('report.id');

        $this->resolve($moderator, $reportId, ['outcome' => 'dismissed', 'penalty' => 'manipulation', 'delete_message' => true])
            ->assertOk()->assertJsonPath('report.status', 'dismissed');

        $this->assertSame(0, $this->xp($reporter));
        $this->assertSame(30, $this->xp($author));
        $this->assertFalse((bool) $message->fresh()->is_deleted);
        $this->assertSame(0, CpLog::whereIn('source', ['report_valid', 'penalty'])->count());
    }

    public function test_a_report_can_only_be_resolved_once(): void
    {
        $author = $this->member();
        $reporter = $this->member();
        $moderator = $this->member('moderator');
        $reportId = $this->report($reporter, $this->message($author))->json('report.id');

        $this->resolve($moderator, $reportId, ['outcome' => 'valid'])->assertOk();
        $this->resolve($moderator, $reportId, ['outcome' => 'valid'])
            ->assertStatus(422)->assertJsonPath('errors.report.0', 'This report has already been resolved.');

        $this->assertSame(1, CpLog::where('source', 'report_valid')->count());
    }

    public function test_regular_members_and_other_rooms_cannot_resolve(): void
    {
        $author = $this->member();
        $reporter = $this->member();
        $reportId = $this->report($reporter, $this->message($author))->json('report.id');

        $this->resolve($reporter, $reportId, ['outcome' => 'valid'])->assertStatus(422);
        $this->assertSame('pending', Report::find($reportId)->status->value);

        // Moderator room lain tidak bisa menutup laporan room ini.
        $otherRoom = Room::factory()->create();
        $otherMod = User::factory()->create();
        Member::create([
            'room_id' => $otherRoom->id, 'user_id' => $otherMod->id, 'role' => 'moderator', 'xp_points' => 0,
            'is_banned' => false, 'joined_at' => now(), 'last_read_at' => now(),
        ]);
        $this->actingAs($otherMod)
            ->postJson("/api/rooms/{$otherRoom->slug}/reports/{$reportId}/resolve", ['outcome' => 'valid'])
            ->assertStatus(422)->assertJsonPath('errors.report.0', 'Report not found.');

        $this->assertSame('pending', Report::find($reportId)->status->value);
    }

    public function test_resolve_validates_the_payload(): void
    {
        $author = $this->member();
        $reporter = $this->member();
        $moderator = $this->member('moderator');
        $reportId = $this->report($reporter, $this->message($author))->json('report.id');

        $this->resolve($moderator, $reportId, ['outcome' => 'maybe'])->assertStatus(422)->assertJsonValidationErrors(['outcome']);
        $this->resolve($moderator, $reportId, ['outcome' => 'valid', 'penalty' => 'ban'])->assertStatus(422)->assertJsonValidationErrors(['penalty']);
    }
}
