<?php

namespace App\Modules\Discuss\tests\Feature;

use App\Modules\Auth\Models\User;
use App\Modules\Discuss\Models\Member;
use App\Modules\Discuss\Models\Message;
use App\Modules\Discuss\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MessageFormattingTest extends TestCase
{
    use RefreshDatabase;

    private function makeMember(Room $room, User $user): void
    {
        Member::create([
            'room_id' => $room->id,
            'user_id' => $user->id,
            'role' => 'member',
            'is_banned' => false,
            'joined_at' => now(),
            'last_read_at' => now(),
        ]);
    }

    /** @return array{0: User, 1: Room} */
    private function roomWithMember(): array
    {
        $user = User::factory()->create();
        $room = Room::factory()->create();
        $this->makeMember($room, $user);

        return [$user, $room];
    }

    public function test_send_stores_entities_in_metadata(): void
    {
        [$user, $room] = $this->roomWithMember();

        $response = $this->actingAs($user)->postJson("/api/rooms/{$room->slug}/messages", [
            'body' => 'Hello brave world',
            'entities' => [
                ['type' => 'italic', 'offset' => 6, 'length' => 5],
                ['type' => 'bold', 'offset' => 0, 'length' => 5],
                ['type' => 'link', 'offset' => 12, 'length' => 5, 'url' => 'https://example.com'],
            ],
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('message.metadata.entities.0.type', 'bold');
        $response->assertJsonPath('message.metadata.entities.2.url', 'https://example.com');

        $message = Message::first();
        $this->assertSame('Hello brave world', $message->body);
        $this->assertCount(3, $message->metadata['entities']);
    }

    public function test_send_without_entities_keeps_metadata_clean(): void
    {
        [$user, $room] = $this->roomWithMember();

        $this->actingAs($user)
            ->postJson("/api/rooms/{$room->slug}/messages", ['body' => 'plain'])
            ->assertStatus(201);

        $this->assertArrayNotHasKey('entities', Message::first()->metadata ?? []);
    }

    public function test_unsafe_link_and_out_of_range_entities_are_dropped(): void
    {
        [$user, $room] = $this->roomWithMember();

        $this->actingAs($user)->postJson("/api/rooms/{$room->slug}/messages", [
            'body' => 'click me',
            'entities' => [
                ['type' => 'link', 'offset' => 0, 'length' => 5, 'url' => 'javascript:alert(1)'],
                ['type' => 'bold', 'offset' => 6, 'length' => 50],
                ['type' => 'bold', 'offset' => 0, 'length' => 5],
            ],
        ])->assertStatus(201);

        $entities = Message::first()->metadata['entities'];
        $this->assertSame([['type' => 'bold', 'offset' => 0, 'length' => 5]], $entities);
    }

    public function test_unknown_entity_type_is_rejected(): void
    {
        [$user, $room] = $this->roomWithMember();

        $this->actingAs($user)->postJson("/api/rooms/{$room->slug}/messages", [
            'body' => 'Hello',
            'entities' => [['type' => 'script', 'offset' => 0, 'length' => 5]],
        ])->assertStatus(422)->assertJsonValidationErrors(['entities.0.type']);

        $this->assertSame(0, Message::count());
    }

    public function test_client_cannot_inject_entities_through_metadata(): void
    {
        [$user, $room] = $this->roomWithMember();

        $this->actingAs($user)->postJson("/api/rooms/{$room->slug}/messages", [
            'body' => 'plain text',
            'metadata' => [
                'entities' => [['type' => 'link', 'offset' => 0, 'length' => 5, 'url' => 'javascript:alert(1)']],
            ],
        ])->assertStatus(201);

        $this->assertArrayNotHasKey('entities', Message::first()->metadata ?? []);
    }

    public function test_edit_replaces_entities(): void
    {
        [$user, $room] = $this->roomWithMember();
        $message = Message::factory()->create([
            'room_id' => $room->id,
            'user_id' => $user->id,
            'body' => 'Original',
            'metadata' => ['entities' => [['type' => 'bold', 'offset' => 0, 'length' => 8]]],
        ]);

        $this->actingAs($user)->putJson("/api/rooms/{$room->slug}/messages/{$message->id}", [
            'body' => 'Edited text',
            'entities' => [['type' => 'spoiler', 'offset' => 7, 'length' => 4]],
        ])->assertStatus(200);

        $this->assertSame(
            [['type' => 'spoiler', 'offset' => 7, 'length' => 4]],
            $message->fresh()->metadata['entities'],
        );
    }

    public function test_edit_without_entities_clears_stale_formatting_but_keeps_other_metadata(): void
    {
        [$user, $room] = $this->roomWithMember();
        $message = Message::factory()->create([
            'room_id' => $room->id,
            'user_id' => $user->id,
            'type' => 'file',
            'body' => 'Original',
            'metadata' => [
                'file' => ['name' => 'report.pdf'],
                'entities' => [['type' => 'bold', 'offset' => 0, 'length' => 8]],
            ],
        ]);

        $this->actingAs($user)->putJson("/api/rooms/{$room->slug}/messages/{$message->id}", [
            'body' => 'Different',
        ])->assertStatus(200);

        $metadata = $message->fresh()->metadata;
        $this->assertArrayNotHasKey('entities', $metadata);
        $this->assertSame('report.pdf', $metadata['file']['name']);
    }

    public function test_other_member_cannot_change_entities(): void
    {
        [$owner, $room] = $this->roomWithMember();
        $attacker = User::factory()->create();
        $this->makeMember($room, $attacker);

        $message = Message::factory()->create([
            'room_id' => $room->id,
            'user_id' => $owner->id,
            'body' => 'Original',
            'metadata' => [],
        ]);

        $this->actingAs($attacker)->putJson("/api/rooms/{$room->slug}/messages/{$message->id}", [
            'body' => 'Original',
            'entities' => [['type' => 'bold', 'offset' => 0, 'length' => 8]],
        ])->assertStatus(422);

        $this->assertArrayNotHasKey('entities', $message->fresh()->metadata ?? []);
    }
}
