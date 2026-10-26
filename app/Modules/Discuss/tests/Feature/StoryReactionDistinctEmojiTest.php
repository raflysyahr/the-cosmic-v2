<?php

namespace App\Modules\Discuss\tests\Feature;

use App\Modules\Auth\Models\User;
use App\Modules\Discuss\Models\Announcement;
use App\Modules\Discuss\Models\AnnouncementReaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Regresi: di MySQL/MariaDB dengan collation utf8mb4_unicode_ci, emoji non-BMP (👍 😂 😮 😢 🔥)
 * dianggap sama oleh GROUP BY, sehingga reaksi berbeda tergabung menjadi satu emoji di UI.
 */
class StoryReactionDistinctEmojiTest extends TestCase
{
    use RefreshDatabase;

    private function post(): Announcement
    {
        return Announcement::create([
            'title' => 'Hello', 'body' => 'World', 'is_pinned' => false, 'published_at' => now()->subHour(),
        ]);
    }

    private function react(User $user, Announcement $post, string $emoji): array
    {
        return $this->actingAs($user)
            ->postJson("/api/discuss/story/{$post->id}/reaction", ['emoji' => $emoji])
            ->assertOk()
            ->json();
    }

    private function member(): User
    {
        return User::factory()->create(['created_at' => now()->subDays(30)]);
    }

    public function test_every_palette_emoji_is_counted_on_its_own(): void
    {
        $post = $this->post();

        $last = [];
        foreach (AnnouncementReaction::PALETTE as $emoji) {
            $last = $this->react($this->member(), $post, $emoji);
        }

        $expected = array_map(fn (string $e) => ['emoji' => $e, 'count' => 1], AnnouncementReaction::PALETTE);
        $this->assertSame($expected, $last['reactions']);
        $this->assertSame(count(AnnouncementReaction::PALETTE), $last['reaction_total']);
    }

    public function test_someone_elses_reaction_never_merges_with_mine(): void
    {
        $post = $this->post();
        $alice = $this->member();
        $bob = $this->member();

        $this->react($alice, $post, '👍');

        // Bob memilih emoji lain: kedua reaksi harus tampil terpisah.
        $res = $this->react($bob, $post, '😂');
        $this->assertSame([['emoji' => '👍', 'count' => 1], ['emoji' => '😂', 'count' => 1]], $res['reactions']);
        $this->assertSame('😂', $res['my_reaction']);

        // Bob menekan lagi emoji yang sama = membatalkan; hanya reaksi Alice yang tersisa.
        $res = $this->react($bob, $post, '😂');
        $this->assertSame([['emoji' => '👍', 'count' => 1]], $res['reactions']);
        $this->assertNull($res['my_reaction']);

        // Bob mengganti ke emoji lain; jumlah 👍 tidak ikut bertambah.
        $res = $this->react($bob, $post, '🔥');
        $this->assertSame([['emoji' => '👍', 'count' => 1], ['emoji' => '🔥', 'count' => 1]], $res['reactions']);
        $this->assertSame('🔥', $res['my_reaction']);
    }

    public function test_emoji_column_is_compared_byte_for_byte_on_mysql_and_mariadb(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            $this->markTestSkipped('Collation hanya relevan untuk MySQL/MariaDB.');
        }

        $column = collect(DB::select('SHOW FULL COLUMNS FROM discuss_announcement_reactions'))
            ->firstWhere('Field', 'emoji');

        $this->assertSame('utf8mb4_bin', $column->Collation);
    }
}
