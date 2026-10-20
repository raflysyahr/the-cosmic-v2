<?php

namespace App\Modules\Discuss\tests\Feature;

use App\Modules\Auth\Models\User;
use App\Modules\Discuss\Http\Resources\MessageResource;
use App\Modules\Discuss\Models\Member;
use App\Modules\Discuss\Models\Message;
use App\Modules\Discuss\Models\Room;
use App\Modules\Discuss\Services\RoomService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class MessageAccessScopeTest extends TestCase
{
    use RefreshDatabase;

    private function addMember(Room $room, User $user, string $role = 'member'): Member
    {
        return Member::create([
            'room_id' => $room->id,
            'user_id' => $user->id,
            'role' => $role,
            'is_banned' => false,
            'joined_at' => now(),
            'last_read_at' => now(),
        ]);
    }

    // ── reply_to_id harus satu room ──

    public function test_cannot_reply_to_a_message_from_another_room(): void
    {
        $user = User::factory()->create();
        $publicRoom = Room::factory()->create(['type' => 'public']);
        $privateRoom = Room::factory()->create(['type' => 'private']);
        $this->addMember($publicRoom, $user);
        $secret = Message::factory()->create(['room_id' => $privateRoom->id, 'body' => 'isi rahasia room privat']);

        $response = $this->actingAs($user)->postJson("/api/rooms/{$publicRoom->slug}/messages", [
            'body' => 'numpang kutip',
            'reply_to_id' => $secret->id,
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['reply_to_id']);
        $this->assertStringNotContainsString('isi rahasia room privat', $response->getContent());
        $this->assertDatabaseMissing('discuss_messages', ['room_id' => $publicRoom->id, 'body' => 'numpang kutip']);
    }

    public function test_cannot_reply_to_a_nonexistent_message(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->create(['type' => 'public']);
        $this->addMember($room, $user);

        $this->actingAs($user)->postJson("/api/rooms/{$room->slug}/messages", [
            'body' => 'halo',
            'reply_to_id' => '01ZZZZZZZZZZZZZZZZZZZZZZZZ',
        ])->assertUnprocessable()->assertJsonValidationErrors(['reply_to_id']);
    }

    public function test_can_reply_to_a_message_in_the_same_room(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->create(['type' => 'public']);
        $this->addMember($room, $user);
        $parent = Message::factory()->create(['room_id' => $room->id, 'body' => 'induk']);

        $this->actingAs($user)->postJson("/api/rooms/{$room->slug}/messages", [
            'body' => 'balasan',
            'reply_to_id' => $parent->id,
        ])->assertCreated()->assertJsonPath('message.reply_to.body', 'induk');
    }

    public function test_resource_never_quotes_a_parent_from_another_room(): void
    {
        $roomA = Room::factory()->create(['type' => 'public']);
        $roomB = Room::factory()->create(['type' => 'private']);
        $foreign = Message::factory()->create(['room_id' => $roomB->id, 'body' => 'isi room lain']);
        // Data lama yang sudah terlanjur menunjuk ke room lain.
        $reply = Message::factory()->create(['room_id' => $roomA->id, 'reply_to_id' => $foreign->id]);

        $payload = (new MessageResource($reply))->toArray(Request::create('/'));

        $this->assertNull($payload['reply_to']);
    }

    // ── type system hanya dari server ──

    public function test_client_cannot_send_system_message(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->create(['type' => 'public']);
        $this->addMember($room, $user);

        $this->actingAs($user)->postJson("/api/rooms/{$room->slug}/messages", [
            'body' => 'Admin added Budi to the group',
            'type' => 'system',
        ])->assertUnprocessable()->assertJsonValidationErrors(['type']);

        $this->assertDatabaseMissing('discuss_messages', ['room_id' => $room->id, 'type' => 'system']);
    }

    public function test_regular_message_types_are_still_accepted(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->create(['type' => 'public']);
        $this->addMember($room, $user);

        $this->actingAs($user)->postJson("/api/rooms/{$room->slug}/messages", [
            'body' => 'teks biasa',
            'type' => 'text',
        ])->assertCreated();
    }

    // ── daftar member room non-publik ──

    public function test_non_member_cannot_list_members_of_private_room(): void
    {
        $member = User::factory()->create();
        $outsider = User::factory()->create();
        $room = Room::factory()->create(['type' => 'private']);
        $this->addMember($room, $member);

        $this->actingAs($outsider)->getJson("/api/rooms/{$room->slug}/members")->assertForbidden();
        $this->actingAs($member)->getJson("/api/rooms/{$room->slug}/members")->assertOk();
    }

    public function test_third_party_cannot_list_members_of_a_direct_chat(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $eve = User::factory()->create();
        $dm = app(RoomService::class)->findOrCreateDirectChat($alice->id, $bob->id);

        $this->actingAs($eve)->getJson("/api/rooms/{$dm->slug}/members")->assertForbidden();
        $this->actingAs($alice)->getJson("/api/rooms/{$dm->slug}/members")->assertOk();
    }

    public function test_banned_member_cannot_list_members_of_private_room(): void
    {
        $banned = User::factory()->create();
        $room = Room::factory()->create(['type' => 'invite_only']);
        $this->addMember($room, $banned)->update(['is_banned' => true]);

        $this->actingAs($banned)->getJson("/api/rooms/{$room->slug}/members")->assertForbidden();
    }

    public function test_anyone_can_list_members_of_public_room(): void
    {
        $viewer = User::factory()->create();
        $room = Room::factory()->create(['type' => 'public']);

        $this->actingAs($viewer)->getJson("/api/rooms/{$room->slug}/members")->assertOk();
    }

    // ── update/destroy harus sesuai room di URL ──

    public function test_cannot_delete_a_message_through_another_rooms_url(): void
    {
        $author = User::factory()->create();
        $roomA = Room::factory()->create(['type' => 'public']);
        $roomB = Room::factory()->create(['type' => 'public']);
        $this->addMember($roomA, $author);
        $this->addMember($roomB, $author);
        $message = Message::factory()->create(['room_id' => $roomB->id, 'user_id' => $author->id]);

        $this->actingAs($author)->deleteJson("/api/rooms/{$roomA->slug}/messages/{$message->id}")->assertNotFound();

        $this->assertFalse((bool) $message->fresh()->is_deleted);

        $this->actingAs($author)->deleteJson("/api/rooms/{$roomB->slug}/messages/{$message->id}")->assertOk();
        $this->assertTrue((bool) $message->fresh()->is_deleted);
    }

    public function test_cannot_edit_a_message_through_another_rooms_url(): void
    {
        $author = User::factory()->create();
        $roomA = Room::factory()->create(['type' => 'public']);
        $roomB = Room::factory()->create(['type' => 'public']);
        $this->addMember($roomA, $author);
        $this->addMember($roomB, $author);
        $message = Message::factory()->create(['room_id' => $roomB->id, 'user_id' => $author->id, 'body' => 'asli']);

        $this->actingAs($author)
            ->putJson("/api/rooms/{$roomA->slug}/messages/{$message->id}", ['body' => 'diubah'])
            ->assertNotFound();

        $this->assertSame('asli', $message->fresh()->body);
    }
}
