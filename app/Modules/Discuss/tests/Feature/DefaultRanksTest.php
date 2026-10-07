<?php

namespace App\Modules\Discuss\tests\Feature;

use App\Modules\Auth\Models\User;
use App\Modules\Discuss\Database\Seeders\DefaultRanksSeeder;
use App\Modules\Discuss\Models\Member;
use App\Modules\Discuss\Models\Rank;
use App\Modules\Discuss\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DefaultRanksTest extends TestCase
{
    use RefreshDatabase;

    private const EXPECTED = [
        1 => ['Outer Disciple', 0],
        2 => ['Inner Disciple', 500],
        3 => ['Core Disciple', 1500],
        4 => ['True Disciple', 3000],
        5 => ['Elite Disciple', 5500],
        6 => ['Elder Disciple', 9000],
        7 => ['Heavenly Disciple', 14000],
        8 => ['Saint Disciple', 21000],
        9 => ['Immortal Disciple', 30000],
        10 => ['Ancient Disciple', 42000],
        11 => ['Eternal Disciple', 58000],
        12 => ['Celestial Disciple', 80000],
    ];

    private function migration(): object
    {
        return require base_path('app/Modules/Discuss/Database/Migrations/2026_10_07_000001_update_discuss_default_ranks.php');
    }

    private function member(Room $room, int $xp, ?string $rankId = null): Member
    {
        return Member::create([
            'room_id' => $room->id, 'user_id' => User::factory()->create()->id, 'role' => 'member',
            'xp_points' => $xp, 'rank_id' => $rankId, 'is_banned' => false,
            'joined_at' => now(), 'last_read_at' => now(),
        ]);
    }

    private function assertDefaultRanks(): void
    {
        $ranks = Rank::global()->orderBy('order')->get();

        $this->assertCount(12, $ranks);
        foreach ($ranks as $rank) {
            [$name, $minXp] = self::EXPECTED[$rank->order];
            $this->assertSame($name, $rank->name);
            $this->assertSame($minXp, $rank->min_xp);
        }
    }

    public function test_seeder_creates_the_twelve_ranks_and_is_idempotent(): void
    {
        $this->seed(DefaultRanksSeeder::class);
        $this->seed(DefaultRanksSeeder::class);

        $this->assertDefaultRanks();
    }

    public function test_migration_upgrades_old_default_ranks_and_recalculates_members(): void
    {
        $old = [
            [1, 'Newcomer', 0], [2, 'Regular', 100], [3, 'Veteran', 500], [4, 'Legend', 2000],
        ];
        $oldIds = [];
        foreach ($old as [$order, $name, $minXp]) {
            $oldIds[$order] = Rank::create([
                'name' => $name, 'label_color' => '#000000', 'min_xp' => $minXp, 'order' => $order,
            ])->id;
        }

        $room = Room::factory()->create();
        $veteran = $this->member($room, 600, $oldIds[3]);   // dulu Veteran, sekarang Inner Disciple
        $legend = $this->member($room, 2500, $oldIds[4]);   // dulu Legend, sekarang Core Disciple
        $high = $this->member($room, 85000, $oldIds[4]);    // naik ke Celestial Disciple
        $fresh = $this->member($room, 0, null);             // tanpa rank tetap tanpa rank

        $this->migration()->up();

        $this->assertDefaultRanks();
        // Baris lama diperbarui di tempat (ID tidak berubah).
        $this->assertSame('Outer Disciple', Rank::find($oldIds[1])->name);

        $rankOf = fn (Member $m) => Rank::find($m->fresh()->rank_id)?->name;
        $this->assertSame('Inner Disciple', $rankOf($veteran));
        $this->assertSame('Core Disciple', $rankOf($legend));
        $this->assertSame('Celestial Disciple', $rankOf($high));
        $this->assertNull($fresh->fresh()->rank_id);

        // Aman dijalankan ulang.
        $this->migration()->up();
        $this->assertDefaultRanks();
    }

    public function test_migration_leaves_a_fresh_install_and_custom_ranks_alone(): void
    {
        $this->migration()->up();
        $this->assertSame(0, Rank::count());

        Rank::create(['name' => 'Moderator Club', 'label_color' => '#000000', 'min_xp' => 10, 'order' => 1]);
        $this->migration()->up();

        $this->assertSame(1, Rank::count());
        $this->assertSame('Moderator Club', Rank::first()->name);
    }
}
