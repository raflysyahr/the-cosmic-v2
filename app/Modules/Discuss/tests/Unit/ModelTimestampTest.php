<?php

namespace App\Modules\Discuss\tests\Unit;

use App\Modules\Auth\Models\User;
use App\Modules\Discuss\Models\Emote;
use App\Modules\Discuss\Models\Member;
use App\Modules\Discuss\Models\Message;
use App\Modules\Discuss\Models\Notification;
use App\Modules\Discuss\Models\Rank;
use App\Modules\Discuss\Models\Reaction;
use App\Modules\Discuss\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Several Discuss models have a `created_at` column but $timestamps = false
 * (correct, since these tables have no `updated_at` column and Eloquent's
 * automatic timestamp support manages both together). Without a manual
 * `creating` hook and explicit datetime cast, `created_at` silently stayed
 * NULL forever, or came back as a raw string instead of a Carbon instance.
 * This test class guards against both regressions.
 */
class ModelTimestampTest extends TestCase
{
    use RefreshDatabase;

    public function test_reaction_created_at_is_populated(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->create();
        $message = Message::factory()->create(['room_id' => $room->id]);

        $reaction = Reaction::create([
            'message_id' => $message->id,
            'user_id' => $user->id,
            'emote_id' => (string) \Illuminate\Support\Str::ulid(),
        ]);

        $this->assertInstanceOf(Carbon::class, $reaction->fresh()->created_at);
    }

    public function test_emote_created_at_is_populated(): void
    {
        $emote = Emote::create([
            'code' => 'test_emote',
            'name' => 'Test Emote',
            'image_url' => 'https://example.com/emote.png',
            'is_animated' => false,
            'is_active' => true,
        ]);

        $this->assertInstanceOf(Carbon::class, $emote->fresh()->created_at);
    }

    public function test_rank_created_at_is_populated(): void
    {
        $rank = Rank::create([
            'name' => 'Newcomer',
            'label_color' => '#666666',
            'min_xp' => 0,
            'order' => 1,
        ]);

        $this->assertInstanceOf(Carbon::class, $rank->fresh()->created_at);
    }

    public function test_notification_created_at_is_populated(): void
    {
        $user = User::factory()->create();

        $notification = Notification::create([
            'user_id' => $user->id,
            'type' => 'mention',
            'payload' => [],
            'is_read' => false,
        ]);

        $this->assertInstanceOf(Carbon::class, $notification->fresh()->created_at);
    }

    public function test_notifications_are_ordered_newest_first(): void
    {
        $user = User::factory()->create();

        $older = Notification::create([
            'user_id' => $user->id,
            'type' => 'mention',
            'payload' => [],
            'is_read' => false,
        ]);
        $older->update(['created_at' => now()->subMinutes(10)]);

        $newer = Notification::create([
            'user_id' => $user->id,
            'type' => 'reply',
            'payload' => [],
            'is_read' => false,
        ]);

        $ordered = Notification::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->pluck('id')
            ->all();

        $this->assertEquals([$newer->id, $older->id], $ordered);
    }
}
