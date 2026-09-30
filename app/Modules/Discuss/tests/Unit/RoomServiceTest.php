<?php

namespace App\Modules\Discuss\tests\Unit;

use App\Modules\Auth\Models\User;
use App\Modules\Discuss\Models\Member;
use App\Modules\Discuss\Models\Room;
use App\Modules\Discuss\Services\MessageService;
use App\Modules\Discuss\Data\SendMessageData;
use App\Modules\Discuss\Services\RoomService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoomServiceTest extends TestCase
{
    use RefreshDatabase;

    private RoomService $roomService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->roomService = app(RoomService::class);
    }

    public function test_creates_new_direct_chat_between_two_users(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $room = $this->roomService->findOrCreateDirectChat($user1->id, $user2->id);

        $this->assertEquals('direct', $room->context_type);
        $this->assertTrue($room->type->value === 'private');
        $this->assertTrue($room->settings['is_direct']);

        $this->assertDatabaseHas('discuss_members', [
            'room_id' => $room->id,
            'user_id' => $user1->id,
        ]);
        $this->assertDatabaseHas('discuss_members', [
            'room_id' => $room->id,
            'user_id' => $user2->id,
        ]);
    }

    public function test_returns_same_room_regardless_of_argument_order(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $roomA = $this->roomService->findOrCreateDirectChat($user1->id, $user2->id);
        $roomB = $this->roomService->findOrCreateDirectChat($user2->id, $user1->id);

        $this->assertEquals($roomA->id, $roomB->id);
    }

    public function test_does_not_create_duplicate_room_on_repeated_calls(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $this->roomService->findOrCreateDirectChat($user1->id, $user2->id);
        $this->roomService->findOrCreateDirectChat($user1->id, $user2->id);

        $count = Room::where('context_type', 'direct')->count();

        $this->assertEquals(1, $count);
    }

    public function test_get_direct_chats_for_user_returns_other_participant_info(): void
    {
        $user1 = User::factory()->create(['display_name' => 'Alice']);
        $user2 = User::factory()->create(['display_name' => 'Bob']);

        $this->roomService->findOrCreateDirectChat($user1->id, $user2->id);

        $chats = $this->roomService->getDirectChatsForUser($user1->id);

        $this->assertCount(1, $chats);
        $this->assertEquals('Bob', $chats[0]['other_user']['display_name']);
    }

    public function test_get_direct_chats_excludes_chats_user_is_not_part_of(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $outsider = User::factory()->create();

        $this->roomService->findOrCreateDirectChat($user1->id, $user2->id);

        $chats = $this->roomService->getDirectChatsForUser($outsider->id);

        $this->assertCount(0, $chats);
    }

    public function test_get_direct_chats_includes_last_message(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $room = $this->roomService->findOrCreateDirectChat($user1->id, $user2->id);

        $messageService = app(MessageService::class);
        $messageService->send(new SendMessageData(
            roomId: $room->id,
            userId: $user1->id,
            replyToId: null,
            type: 'text',
            body: 'Hi there',
            attachments: [],
            metadata: [],
        ));

        $chats = $this->roomService->getDirectChatsForUser($user2->id);

        $this->assertEquals('Hi there', $chats[0]['last_message']['body']);
    }

    public function test_get_direct_recipient_returns_the_other_participant(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create(['display_name' => 'Bob']);

        $room = $this->roomService->findOrCreateDirectChat($user1->id, $user2->id);

        $recipient = $this->roomService->getDirectRecipient($room, $user1->id);

        $this->assertNotNull($recipient);
        $this->assertEquals('Bob', $recipient['display_name']);
        $this->assertEquals($user2->id, $recipient['id']);
    }

    public function test_get_direct_recipient_returns_null_for_non_direct_room(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->create(['type' => 'public', 'context_type' => null]);

        $recipient = $this->roomService->getDirectRecipient($room, $user->id);

        $this->assertNull($recipient);
    }

    public function test_get_direct_recipient_returns_null_when_other_participant_left(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $room = $this->roomService->findOrCreateDirectChat($user1->id, $user2->id);

        Member::where('room_id', $room->id)
            ->where('user_id', $user2->id)
            ->delete();

        $recipient = $this->roomService->getDirectRecipient($room, $user1->id);

        $this->assertNull($recipient);
    }
}
