<?php

namespace App\Modules\Discuss\tests\Feature;

use App\Modules\Auth\Models\User;
use App\Modules\Discuss\Models\Member;
use App\Modules\Discuss\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class RateLimitAndSlowModeTest extends TestCase
{
    use RefreshDatabase;

    private function roomWithMember(User $user, array $settings = [], string $role = 'member', array $roomAttrs = []): Room
    {
        $room = Room::factory()->create(array_merge([
            'type' => 'public',
            'settings' => array_merge(['slow_mode_seconds' => 0, 'max_members' => 500, 'xp_per_message' => 5], $settings),
        ], $roomAttrs));

        Member::create([
            'room_id' => $room->id,
            'user_id' => $user->id,
            'role' => $role,
            'is_banned' => false,
            'joined_at' => now(),
            'last_read_at' => now(),
        ]);

        return $room;
    }

    private function assertNotThrottled(TestResponse $response): void
    {
        $this->assertNotSame(429, $response->getStatusCode(), 'Request seharusnya belum kena rate limit.');
    }

    // ── Auth ──

    public function test_login_is_throttled_per_email_and_ip(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->assertNotThrottled($this->postJson('/api/login', ['email' => 'korban@example.com', 'password' => 'salah']));
        }

        $this->postJson('/api/login', ['email' => 'korban@example.com', 'password' => 'salah'])
            ->assertStatus(429);

        // Email lain dari IP yang sama belum terkena batas per-email.
        $this->assertNotThrottled($this->postJson('/api/login', ['email' => 'lain@example.com', 'password' => 'salah']));
    }

    public function test_login_throttle_ignores_email_case(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/login', ['email' => 'Korban@Example.com', 'password' => 'salah']);
        }

        $this->postJson('/api/login', ['email' => 'korban@example.com', 'password' => 'salah'])
            ->assertStatus(429);
    }

    public function test_register_is_throttled_per_ip(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->assertNotThrottled($this->postJson('/api/register', []));
        }

        $this->postJson('/api/register', [])->assertStatus(429);
    }

    public function test_forgot_password_is_throttled_per_email(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->assertNotThrottled($this->postJson('/api/forgot-password', ['email' => 'korban@example.com']));
        }

        $this->postJson('/api/forgot-password', ['email' => 'korban@example.com'])->assertStatus(429);
    }

    public function test_429_response_carries_retry_after(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/login', ['email' => 'korban@example.com', 'password' => 'salah']);
        }

        $this->postJson('/api/login', ['email' => 'korban@example.com', 'password' => 'salah'])
            ->assertStatus(429)
            ->assertHeader('Retry-After');
    }

    // ── Discuss ──

    public function test_sending_messages_is_throttled_per_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $room = $this->roomWithMember($user);
        Member::create([
            'room_id' => $room->id, 'user_id' => $other->id, 'role' => 'member',
            'is_banned' => false, 'joined_at' => now(), 'last_read_at' => now(),
        ]);

        for ($i = 0; $i < 20; $i++) {
            $this->actingAs($user)->postJson("/api/rooms/{$room->slug}/messages", ['body' => "pesan {$i}"])
                ->assertCreated();
        }

        $this->actingAs($user)->postJson("/api/rooms/{$room->slug}/messages", ['body' => 'terlalu banyak'])
            ->assertStatus(429);

        // Kuota per user: user lain tidak ikut terblokir.
        $this->actingAs($other)->postJson("/api/rooms/{$room->slug}/messages", ['body' => 'masih boleh'])
            ->assertCreated();
    }

    public function test_requests_with_files_also_count_against_the_upload_limit(): void
    {
        $user = User::factory()->create();
        $resolve = RateLimiter::limiter('discuss-send');

        $plain = Request::create('/api/rooms/x/messages', 'POST');
        $plain->setUserResolver(fn () => $user);
        $withFile = Request::create('/api/rooms/x/messages', 'POST', [], [], ['image' => UploadedFile::fake()->image('a.jpg')]);
        $withFile->setUserResolver(fn () => $user);

        $this->assertCount(1, $resolve($plain));
        $this->assertCount(3, $resolve($withFile));
    }

    public function test_room_write_actions_are_throttled(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->create(['type' => 'public']);

        for ($i = 0; $i < 30; $i++) {
            $this->assertNotThrottled($this->actingAs($user)->postJson("/api/rooms/{$room->slug}/join"));
        }

        $this->actingAs($user)->postJson("/api/rooms/{$room->slug}/join")->assertStatus(429);
    }

    public function test_reports_are_throttled_more_tightly(): void
    {
        $user = User::factory()->create();
        $room = $this->roomWithMember($user);

        for ($i = 0; $i < 10; $i++) {
            $this->assertNotThrottled($this->actingAs($user)->postJson("/api/rooms/{$room->slug}/messages/01HZZZZZZZZZZZZZZZZZZZZZZZ/report", ['reason' => 'spam']));
        }

        $this->actingAs($user)->postJson("/api/rooms/{$room->slug}/messages/01HZZZZZZZZZZZZZZZZZZZZZZZ/report", ['reason' => 'spam'])
            ->assertStatus(429);
    }

    // ── Slow mode ──

    public function test_slow_mode_blocks_a_second_message_inside_the_window(): void
    {
        $user = User::factory()->create();
        $room = $this->roomWithMember($user, ['slow_mode_seconds' => 30]);

        $this->actingAs($user)->postJson("/api/rooms/{$room->slug}/messages", ['body' => 'pertama'])->assertCreated();

        $this->actingAs($user)->postJson("/api/rooms/{$room->slug}/messages", ['body' => 'kedua'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['message']);

        $this->assertDatabaseMissing('discuss_messages', ['room_id' => $room->id, 'body' => 'kedua']);
    }

    public function test_slow_mode_allows_a_message_after_the_window(): void
    {
        $user = User::factory()->create();
        $room = $this->roomWithMember($user, ['slow_mode_seconds' => 30]);

        $this->actingAs($user)->postJson("/api/rooms/{$room->slug}/messages", ['body' => 'pertama'])->assertCreated();

        $this->travel(31)->seconds();

        $this->actingAs($user)->postJson("/api/rooms/{$room->slug}/messages", ['body' => 'kedua'])->assertCreated();
    }

    public function test_slow_mode_is_per_user_and_per_room(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $room = $this->roomWithMember($alice, ['slow_mode_seconds' => 30]);
        Member::create([
            'room_id' => $room->id, 'user_id' => $bob->id, 'role' => 'member',
            'is_banned' => false, 'joined_at' => now(), 'last_read_at' => now(),
        ]);
        $otherRoom = $this->roomWithMember($alice, ['slow_mode_seconds' => 30]);

        $this->actingAs($alice)->postJson("/api/rooms/{$room->slug}/messages", ['body' => 'a1'])->assertCreated();
        $this->actingAs($bob)->postJson("/api/rooms/{$room->slug}/messages", ['body' => 'b1'])->assertCreated();
        $this->actingAs($alice)->postJson("/api/rooms/{$otherRoom->slug}/messages", ['body' => 'a-lain'])->assertCreated();
    }

    public function test_slow_mode_does_not_apply_to_moderators_admins_or_owner(): void
    {
        foreach (['moderator', 'admin'] as $role) {
            $user = User::factory()->create();
            $room = $this->roomWithMember($user, ['slow_mode_seconds' => 60], $role);

            $this->actingAs($user)->postJson("/api/rooms/{$room->slug}/messages", ['body' => 'satu'])->assertCreated();
            $this->actingAs($user)->postJson("/api/rooms/{$room->slug}/messages", ['body' => 'dua'])->assertCreated();
        }

        $owner = User::factory()->create();
        $room = $this->roomWithMember($owner, ['slow_mode_seconds' => 60], 'member', ['owner_user_id' => $owner->id]);

        $this->actingAs($owner)->postJson("/api/rooms/{$room->slug}/messages", ['body' => 'satu'])->assertCreated();
        $this->actingAs($owner)->postJson("/api/rooms/{$room->slug}/messages", ['body' => 'dua'])->assertCreated();
    }

    public function test_deleting_a_message_does_not_reset_slow_mode(): void
    {
        $user = User::factory()->create();
        $room = $this->roomWithMember($user, ['slow_mode_seconds' => 30]);

        $id = $this->actingAs($user)->postJson("/api/rooms/{$room->slug}/messages", ['body' => 'pertama'])
            ->assertCreated()->json('message.id');
        $this->actingAs($user)->deleteJson("/api/rooms/{$room->slug}/messages/{$id}")->assertOk();

        $this->actingAs($user)->postJson("/api/rooms/{$room->slug}/messages", ['body' => 'kedua'])
            ->assertUnprocessable();
    }

    public function test_invalid_message_does_not_consume_the_slow_mode_slot(): void
    {
        $user = User::factory()->create();
        $room = $this->roomWithMember($user, ['slow_mode_seconds' => 30]);

        $this->actingAs($user)->postJson("/api/rooms/{$room->slug}/messages", [
            'body' => 'gagal',
            'reply_to_id' => '01ZZZZZZZZZZZZZZZZZZZZZZZZ',
        ])->assertUnprocessable();

        $this->actingAs($user)->postJson("/api/rooms/{$room->slug}/messages", ['body' => 'berhasil'])->assertCreated();
    }

    public function test_no_slow_mode_when_disabled(): void
    {
        $user = User::factory()->create();
        $room = $this->roomWithMember($user, ['slow_mode_seconds' => 0]);

        $this->actingAs($user)->postJson("/api/rooms/{$room->slug}/messages", ['body' => 'satu'])->assertCreated();
        $this->actingAs($user)->postJson("/api/rooms/{$room->slug}/messages", ['body' => 'dua'])->assertCreated();
    }
}
