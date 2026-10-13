<?php

namespace App\Modules\Discuss\tests\Feature;

use App\Modules\Auth\Models\User;
use App\Modules\Discuss\Models\Member;
use App\Modules\Discuss\Models\Message;
use App\Modules\Discuss\Models\Room;
use App\Modules\Discuss\Services\RoomService;
use App\Modules\Discuss\Services\RoomSharedContentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaSpoilerAndPreviewTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: Room} */
    private function roomWithMember(): array
    {
        $user = User::factory()->create();
        $room = Room::factory()->create();
        Member::create([
            'room_id' => $room->id,
            'user_id' => $user->id,
            'role' => 'member',
            'is_banned' => false,
            'joined_at' => now(),
            'last_read_at' => now(),
        ]);

        return [$user, $room];
    }

    public function test_image_can_be_sent_as_spoiler_with_a_formatted_caption(): void
    {
        Storage::fake('public');
        [$user, $room] = $this->roomWithMember();

        $response = $this->actingAs($user)->post("/api/rooms/{$room->slug}/messages", [
            'image' => UploadedFile::fake()->image('photo.jpg'),
            'body' => 'Look at this',
            'spoiler' => '1',
            // Multipart: angka dikirim sebagai string.
            'entities' => [['type' => 'bold', 'offset' => '0', 'length' => '4']],
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('message.metadata.spoiler', true);
        $response->assertJsonPath('message.metadata.entities.0.type', 'bold');
        $response->assertJsonPath('message.metadata.entities.0.length', 4);
    }

    public function test_image_without_spoiler_flag_is_not_marked(): void
    {
        Storage::fake('public');
        [$user, $room] = $this->roomWithMember();

        $this->actingAs($user)->post("/api/rooms/{$room->slug}/messages", [
            'image' => UploadedFile::fake()->image('photo.jpg'),
        ])->assertStatus(201);

        $this->assertArrayNotHasKey('spoiler', Message::first()->metadata ?? []);
    }

    public function test_video_can_be_sent_as_spoiler(): void
    {
        Storage::fake('public');
        [$user, $room] = $this->roomWithMember();

        $this->actingAs($user)->post("/api/rooms/{$room->slug}/messages", [
            'video' => UploadedFile::fake()->create('clip.mp4', 200, 'video/mp4'),
            'spoiler' => '1',
        ])->assertStatus(201)->assertJsonPath('message.metadata.spoiler', true);
    }

    public function test_spoiler_flag_is_ignored_for_text_messages(): void
    {
        [$user, $room] = $this->roomWithMember();

        $this->actingAs($user)->postJson("/api/rooms/{$room->slug}/messages", [
            'body' => 'just text',
            'spoiler' => true,
        ])->assertStatus(201);

        $this->assertArrayNotHasKey('spoiler', Message::first()->metadata ?? []);
    }

    public function test_client_cannot_inject_spoiler_through_metadata(): void
    {
        [$user, $room] = $this->roomWithMember();

        $this->actingAs($user)->postJson("/api/rooms/{$room->slug}/messages", [
            'body' => 'text',
            'metadata' => ['spoiler' => true],
        ])->assertStatus(201);

        $this->assertArrayNotHasKey('spoiler', Message::first()->metadata ?? []);
    }

    public function test_non_boolean_spoiler_value_is_rejected(): void
    {
        [$user, $room] = $this->roomWithMember();

        $this->actingAs($user)->postJson("/api/rooms/{$room->slug}/messages", [
            'body' => 'text',
            'spoiler' => 'maybe',
        ])->assertStatus(422)->assertJsonValidationErrors(['spoiler']);
    }

    public function test_editing_the_caption_keeps_the_spoiler_flag(): void
    {
        [$user, $room] = $this->roomWithMember();
        $message = Message::factory()->create([
            'room_id' => $room->id,
            'user_id' => $user->id,
            'type' => 'image',
            'body' => 'Old caption',
            'attachments' => ['/storage/messages/a.jpg'],
            'metadata' => ['spoiler' => true],
        ]);

        $this->actingAs($user)->putJson("/api/rooms/{$room->slug}/messages/{$message->id}", [
            'body' => 'New caption',
        ])->assertStatus(200);

        $this->assertTrue($message->fresh()->metadata['spoiler']);
    }

    public function test_replacing_the_media_while_editing_resets_the_spoiler_flag(): void
    {
        Storage::fake('public');
        [$user, $room] = $this->roomWithMember();
        $message = Message::factory()->create([
            'room_id' => $room->id,
            'user_id' => $user->id,
            'type' => 'image',
            'body' => 'Caption',
            'attachments' => ['/storage/messages/a.jpg'],
            'metadata' => ['spoiler' => true],
        ]);

        $this->actingAs($user)->post("/api/rooms/{$room->slug}/messages/{$message->id}", [
            '_method' => 'PUT',
            'body' => 'Caption',
            'image' => UploadedFile::fake()->image('new.jpg'),
        ])->assertStatus(200);

        $this->assertArrayNotHasKey('spoiler', $message->fresh()->metadata ?? []);
    }

    public function test_reply_quote_only_carries_spoiler_entities_and_the_media_spoiler_flag(): void
    {
        [$user, $room] = $this->roomWithMember();
        $original = Message::factory()->create([
            'room_id' => $room->id,
            'user_id' => $user->id,
            'type' => 'image',
            'body' => 'secret and bold',
            'attachments' => ['/storage/messages/a.jpg'],
            'metadata' => [
                'spoiler' => true,
                'entities' => [
                    ['type' => 'spoiler', 'offset' => 0, 'length' => 6],
                    ['type' => 'bold', 'offset' => 11, 'length' => 4],
                ],
            ],
        ]);

        $response = $this->actingAs($user)->postJson("/api/rooms/{$room->slug}/messages", [
            'body' => 'replying',
            'reply_to_id' => $original->id,
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('message.reply_to.spoiler', true);
        $this->assertSame(
            [['type' => 'spoiler', 'offset' => 0, 'length' => 6]],
            $response->json('message.reply_to.entities'),
        );
    }

    public function test_last_message_preview_only_carries_spoiler_entities(): void
    {
        [$user, $room] = $this->roomWithMember();
        Message::factory()->create([
            'room_id' => $room->id,
            'user_id' => $user->id,
            'body' => 'secret and bold',
            'metadata' => [
                'entities' => [
                    ['type' => 'bold', 'offset' => 11, 'length' => 4],
                    ['type' => 'spoiler', 'offset' => 0, 'length' => 6],
                ],
            ],
        ]);

        $last = app(RoomService::class)->getLastMessage($room->id);

        $this->assertSame([['type' => 'spoiler', 'offset' => 0, 'length' => 6]], $last['entities']);
    }

    public function test_shared_media_gallery_flags_spoiler_items(): void
    {
        [$user, $room] = $this->roomWithMember();
        Message::factory()->create([
            'room_id' => $room->id,
            'user_id' => $user->id,
            'type' => 'image',
            'attachments' => ['/storage/messages/a.jpg'],
            'metadata' => ['spoiler' => true],
        ]);
        Message::factory()->create([
            'room_id' => $room->id,
            'user_id' => $user->id,
            'type' => 'image',
            'attachments' => ['/storage/messages/b.jpg'],
            'metadata' => [],
        ]);

        $media = app(RoomSharedContentService::class)->forRoom($room->id)['media'];

        $bySpoiler = collect($media)->pluck('spoiler', 'url')->all();
        $this->assertTrue($bySpoiler['/storage/messages/a.jpg']);
        $this->assertFalse($bySpoiler['/storage/messages/b.jpg']);
    }
}
