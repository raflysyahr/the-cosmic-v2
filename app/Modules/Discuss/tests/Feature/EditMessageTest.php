<?php

namespace App\Modules\Discuss\tests\Feature;

use App\Modules\Auth\Models\User;
use App\Modules\Discuss\Models\Member;
use App\Modules\Discuss\Models\Message;
use App\Modules\Discuss\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EditMessageTest extends TestCase
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

    public function test_owner_can_edit_own_message(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->create();
        $this->makeMember($room, $user);

        $message = Message::factory()->create([
            'room_id' => $room->id,
            'user_id' => $user->id,
            'body' => 'Original',
        ]);

        $response = $this->actingAs($user)->putJson(
            "/api/rooms/{$room->slug}/messages/{$message->id}",
            ['body' => 'Updated body']
        );

        $response->assertStatus(200);
        $this->assertEquals('Updated body', $message->fresh()->body);
        $this->assertTrue($message->fresh()->is_edited);
    }

    public function test_other_user_cannot_edit_message(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $room = Room::factory()->create();
        $this->makeMember($room, $owner);
        $this->makeMember($room, $attacker);

        $message = Message::factory()->create([
            'room_id' => $room->id,
            'user_id' => $owner->id,
            'body' => 'Original',
        ]);

        $response = $this->actingAs($attacker)->putJson(
            "/api/rooms/{$room->slug}/messages/{$message->id}",
            ['body' => 'Hacked body']
        );

        $response->assertStatus(422);
        $this->assertEquals('Original', $message->fresh()->body);
        $this->assertFalse($message->fresh()->is_edited);
    }

    public function test_guest_cannot_edit_message(): void
    {
        $owner = User::factory()->create();
        $room = Room::factory()->create();
        $this->makeMember($room, $owner);

        $message = Message::factory()->create([
            'room_id' => $room->id,
            'user_id' => $owner->id,
            'body' => 'Original',
        ]);

        $response = $this->putJson(
            "/api/rooms/{$room->slug}/messages/{$message->id}",
            ['body' => 'Hacked body']
        );

        $response->assertStatus(401);
        $this->assertEquals('Original', $message->fresh()->body);
    }

    public function test_owner_can_add_image_while_editing(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');

        $user = User::factory()->create();
        $room = Room::factory()->create();
        $this->makeMember($room, $user);

        $message = Message::factory()->create([
            'room_id' => $room->id,
            'user_id' => $user->id,
            'body' => 'Original',
        ]);

        // Multipart can't go over PUT, so the client posts with _method=PUT.
        $response = $this->actingAs($user)->post(
            "/api/rooms/{$room->slug}/messages/{$message->id}",
            [
                '_method' => 'PUT',
                'body' => 'With a photo',
                'image' => \Illuminate\Http\UploadedFile::fake()->image('photo.jpg'),
            ]
        );

        $response->assertStatus(200);
        $response->assertJsonPath('message.type', 'image');
        $response->assertJsonPath('message.body', 'With a photo');
        $this->assertCount(1, $message->fresh()->attachments);
        $this->assertTrue($message->fresh()->is_edited);
    }

    public function test_owner_can_add_file_while_editing(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');

        $user = User::factory()->create();
        $room = Room::factory()->create();
        $this->makeMember($room, $user);

        $message = Message::factory()->create([
            'room_id' => $room->id,
            'user_id' => $user->id,
            'body' => 'Original',
        ]);

        $response = $this->actingAs($user)->post(
            "/api/rooms/{$room->slug}/messages/{$message->id}",
            [
                '_method' => 'PUT',
                'body' => 'See attached',
                'file' => \Illuminate\Http\UploadedFile::fake()->create('laporan.pdf', 50, 'application/pdf'),
            ]
        );

        $response->assertStatus(200);
        $response->assertJsonPath('message.type', 'file');
        $response->assertJsonPath('message.metadata.file.name', 'laporan.pdf');
    }

    public function test_cannot_clear_body_of_plain_text_message(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->create();
        $this->makeMember($room, $user);

        $message = Message::factory()->create([
            'room_id' => $room->id,
            'user_id' => $user->id,
            'body' => 'Original',
        ]);

        $response = $this->actingAs($user)->putJson(
            "/api/rooms/{$room->slug}/messages/{$message->id}",
            ['body' => '']
        );

        $response->assertStatus(422);
        $this->assertEquals('Original', $message->fresh()->body);
    }

    public function test_other_user_cannot_replace_attachment(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');

        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $room = Room::factory()->create();
        $this->makeMember($room, $owner);
        $this->makeMember($room, $attacker);

        $message = Message::factory()->create([
            'room_id' => $room->id,
            'user_id' => $owner->id,
            'body' => 'Original',
        ]);

        $response = $this->actingAs($attacker)->post(
            "/api/rooms/{$room->slug}/messages/{$message->id}",
            [
                '_method' => 'PUT',
                'body' => 'Hacked',
                'image' => \Illuminate\Http\UploadedFile::fake()->image('x.jpg'),
            ]
        );

        $response->assertStatus(422);
        $this->assertEmpty($message->fresh()->attachments ?? []);
        // The rejected upload must not stay behind on disk.
        $this->assertEmpty(\Illuminate\Support\Facades\Storage::disk('public')->allFiles('messages'));
    }

    public function test_owner_can_add_video_while_editing(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');

        $user = User::factory()->create();
        $room = Room::factory()->create();
        $this->makeMember($room, $user);

        $message = Message::factory()->create([
            'room_id' => $room->id,
            'user_id' => $user->id,
            'body' => 'Original',
        ]);

        $response = $this->actingAs($user)->post(
            "/api/rooms/{$room->slug}/messages/{$message->id}",
            [
                '_method' => 'PUT',
                'body' => 'With a video',
                'video' => \Illuminate\Http\UploadedFile::fake()->create('clip.mp4', 2048, 'video/mp4'),
                'duration' => 5,
            ]
        );

        $response->assertStatus(200);
        $response->assertJsonPath('message.type', 'video');
        $response->assertJsonPath('message.metadata.video.duration', 5);
        $this->assertTrue($message->fresh()->is_edited);
    }
}
