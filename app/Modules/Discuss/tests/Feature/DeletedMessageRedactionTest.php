<?php

namespace App\Modules\Discuss\tests\Feature;

use App\Modules\Auth\Models\User;
use App\Modules\Discuss\Http\Resources\MessageResource;
use App\Modules\Discuss\Models\Member;
use App\Modules\Discuss\Models\Message;
use App\Modules\Discuss\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class DeletedMessageRedactionTest extends TestCase
{
    use RefreshDatabase;

    private function deletedMessage(Room $room, User $author, array $overrides = []): Message
    {
        return Message::factory()->create(array_merge([
            'room_id' => $room->id,
            'user_id' => $author->id,
            'type' => 'image',
            'body' => 'rahasia yang sudah dihapus',
            'attachments' => ['https://cdn.test/secret.jpg'],
            'metadata' => ['thumbnail' => 'https://cdn.test/secret-thumb.jpg', 'entities' => [['type' => 'spoiler']]],
            'is_deleted' => true,
            'deleted_by_user_id' => $author->id,
        ], $overrides));
    }

    public function test_resource_strips_content_of_deleted_message(): void
    {
        $room = Room::factory()->create(['type' => 'public']);
        $author = User::factory()->create();
        $message = $this->deletedMessage($room, $author);

        $payload = (new MessageResource($message))->toArray(Request::create('/'));

        $this->assertTrue($payload['is_deleted']);
        $this->assertNull($payload['body']);
        $this->assertSame([], $payload['attachments']);
        $this->assertNull($payload['metadata']);
        $this->assertSame('text', $payload['type']);
        $this->assertSame($message->id, $payload['id']);
        $this->assertSame($author->id, $payload['user']['id']);
    }

    public function test_resource_keeps_content_of_live_message(): void
    {
        $room = Room::factory()->create(['type' => 'public']);
        $message = Message::factory()->create([
            'room_id' => $room->id,
            'body' => 'masih ada',
            'is_deleted' => false,
        ]);

        $payload = (new MessageResource($message))->toArray(Request::create('/'));

        $this->assertFalse($payload['is_deleted']);
        $this->assertSame('masih ada', $payload['body']);
    }

    public function test_reply_quote_of_deleted_parent_is_redacted(): void
    {
        $room = Room::factory()->create(['type' => 'public']);
        $author = User::factory()->create();
        $parent = $this->deletedMessage($room, $author);
        $reply = Message::factory()->create([
            'room_id' => $room->id,
            'reply_to_id' => $parent->id,
            'body' => 'balasan',
        ]);

        $quote = (new MessageResource($reply))->toArray(Request::create('/'))['reply_to'];

        $this->assertTrue($quote['is_deleted']);
        $this->assertNull($quote['body']);
        $this->assertNull($quote['thumbnail']);
        $this->assertNull($quote['file_name']);
        $this->assertSame([], $quote['entities']);
        $this->assertSame('text', $quote['type']);
    }

    public function test_room_page_does_not_ship_deleted_message_content(): void
    {
        $room = Room::factory()->create(['type' => 'public']);
        $viewer = User::factory()->create();
        $author = User::factory()->create();
        $this->deletedMessage($room, $author, ['type' => 'text', 'attachments' => []]);

        $response = $this->actingAs($viewer)->get("/discuss/{$room->slug}")->assertOk();

        $response->assertInertia(fn ($page) => $page
            ->where('messages.0.is_deleted', true)
            ->where('messages.0.body', null)
            ->where('messages.0.attachments', []));

        $this->assertStringNotContainsString('rahasia yang sudah dihapus', $response->getContent());
    }

    public function test_deleting_via_api_then_listing_hides_content(): void
    {
        $room = Room::factory()->create(['type' => 'public']);
        $author = User::factory()->create();
        Member::create([
            'room_id' => $room->id,
            'user_id' => $author->id,
            'role' => 'member',
            'is_banned' => false,
            'joined_at' => now(),
            'last_read_at' => now(),
        ]);

        $messageId = $this->actingAs($author)
            ->postJson("/api/rooms/{$room->slug}/messages", ['body' => 'akan dihapus'])
            ->assertCreated()
            ->json('message.id');

        $this->actingAs($author)->deleteJson("/api/rooms/{$room->slug}/messages/{$messageId}")->assertOk();

        $page = $this->actingAs($author)->get("/discuss/{$room->slug}")->assertOk();
        $this->assertStringNotContainsString('akan dihapus', $page->getContent());
    }

    public function test_deleted_message_cannot_be_edited(): void
    {
        $room = Room::factory()->create(['type' => 'public']);
        $author = User::factory()->create();
        $message = $this->deletedMessage($room, $author, ['type' => 'text', 'attachments' => []]);

        $this->actingAs($author)
            ->putJson("/api/rooms/{$room->slug}/messages/{$message->id}", ['body' => 'hidup lagi'])
            ->assertUnprocessable();

        $this->assertSame('rahasia yang sudah dihapus', $message->fresh()->body);
        $this->assertTrue((bool) $message->fresh()->is_deleted);
    }
}
