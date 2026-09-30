<?php

namespace App\Modules\Discuss\tests\Feature;

use App\Modules\Auth\Models\User;
use App\Modules\Discuss\Models\Member;
use App\Modules\Discuss\Models\Message;
use App\Modules\Discuss\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SendMessageTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_send_message(): void
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

        $response = $this->actingAs($user)->postJson("/api/rooms/{$room->slug}/messages", [
            'body' => 'Hello from test!',
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure(['message']);
    }

    public function test_cannot_send_empty_message(): void
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

        $response = $this->actingAs($user)->postJson("/api/rooms/{$room->slug}/messages", []);

        $response->assertStatus(422);
    }

    public function test_cannot_send_when_muted(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->create();

        Member::create([
            'room_id' => $room->id,
            'user_id' => $user->id,
            'role' => 'member',
            'is_banned' => false,
            'muted_until' => now()->addDays(1),
            'joined_at' => now(),
            'last_read_at' => now(),
        ]);

        $response = $this->actingAs($user)->postJson("/api/rooms/{$room->slug}/messages", [
            'body' => 'Test message',
        ]);

        $response->assertStatus(422);
    }

    public function test_can_send_image_message_with_caption(): void
    {
        Storage::fake('public');

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

        $file = UploadedFile::fake()->image('photo.jpg');

        $response = $this->actingAs($user)->post("/api/rooms/{$room->slug}/messages", [
            'image' => $file,
            'body' => 'Look at this!',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('message.type', 'image');
        $response->assertJsonPath('message.body', 'Look at this!');

        $message = Message::findOrFail($response->json('message.id'));
        $this->assertCount(1, $message->attachments);
        Storage::disk('public')->assertExists('messages/' . basename($message->attachments[0]));

        // A chat-bubble thumbnail is generated alongside the full-size original
        // (skipped gracefully if this install's PHP has no GD extension).
        if (function_exists('imagecreatefromstring')) {
            $this->assertNotNull($message->metadata['thumbnail'] ?? null);
            $thumbPath = 'messages/thumbs/' . pathinfo($message->attachments[0], PATHINFO_FILENAME) . '.jpg';
            Storage::disk('public')->assertExists($thumbPath);
        }
    }

    public function test_can_send_image_message_without_caption(): void
    {
        Storage::fake('public');

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

        $file = UploadedFile::fake()->image('photo.jpg');

        $response = $this->actingAs($user)->post("/api/rooms/{$room->slug}/messages", [
            'image' => $file,
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('message.type', 'image');
    }

    public function test_can_send_file_message_with_metadata(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');

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

        $file = \Illuminate\Http\UploadedFile::fake()->create('laporan.pdf', 120, 'application/pdf');

        $response = $this->actingAs($user)->post("/api/rooms/{$room->slug}/messages", [
            'file' => $file,
            'body' => 'Ini laporannya',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('message.type', 'file');
        $response->assertJsonPath('message.body', 'Ini laporannya');
        $response->assertJsonPath('message.metadata.file.name', 'laporan.pdf');
        $this->assertCount(1, $response->json('message.attachments'));
    }

    public function test_rejects_executable_file_upload(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');

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

        $file = \Illuminate\Http\UploadedFile::fake()->create('shell.php', 1, 'application/x-php');

        $response = $this->actingAs($user)->postJson("/api/rooms/{$room->slug}/messages", [
            'file' => $file,
        ]);

        $response->assertStatus(422);
    }

    public function test_can_send_video_message_with_metadata(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');

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

        $video = \Illuminate\Http\UploadedFile::fake()->create('clip.mp4', 2048, 'video/mp4');
        $thumb = \Illuminate\Http\UploadedFile::fake()->image('thumb.jpg');

        $response = $this->actingAs($user)->post("/api/rooms/{$room->slug}/messages", [
            'video' => $video,
            'thumbnail' => $thumb,
            'duration' => 12.5,
            'width' => 1280,
            'height' => 720,
            'body' => 'Lihat ini',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('message.type', 'video');
        $response->assertJsonPath('message.body', 'Lihat ini');
        $response->assertJsonPath('message.metadata.video.duration', 12.5);
        $response->assertJsonPath('message.metadata.video.width', 1280);
        $this->assertNotNull($response->json('message.metadata.video.thumbnail'));
        $this->assertCount(1, $response->json('message.attachments'));
    }

    public function test_rejects_non_video_file_as_video(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');

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

        $file = \Illuminate\Http\UploadedFile::fake()->create('not-a-video.txt', 10, 'text/plain');

        $response = $this->actingAs($user)->postJson("/api/rooms/{$room->slug}/messages", [
            'video' => $file,
        ]);

        $response->assertStatus(422);
    }
}
