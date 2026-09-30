<?php

namespace App\Modules\Discuss\tests\Unit;

use App\Modules\Auth\Models\User;
use App\Modules\Discuss\Data\SendMessageData;
use App\Modules\Discuss\Models\Member;
use App\Modules\Discuss\Models\Message;
use App\Modules\Discuss\Models\Room;
use App\Modules\Discuss\Services\MessageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MessageServiceTest extends TestCase
{
    use RefreshDatabase;

    private MessageService $messageService;
    private Room $room;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->messageService = app(MessageService::class);
        $this->user = User::factory()->create();
        $this->room = Room::factory()->create(['owner_user_id' => $this->user->id]);

        Member::create([
            'room_id' => $this->room->id,
            'user_id' => $this->user->id,
            'role' => 'member',
            'xp_points' => 0,
            'is_banned' => false,
            'joined_at' => now(),
            'last_read_at' => now(),
        ]);
    }

    private function sendAs(User $user, string $body = 'Hello world!'): Message
    {
        $data = new SendMessageData(
            roomId: $this->room->id,
            userId: $user->id,
            replyToId: null,
            type: 'text',
            body: $body,
            attachments: [],
            metadata: [],
        );

        return $this->messageService->send($data);
    }

    public function test_can_send_message(): void
    {
        $message = $this->sendAs($this->user, 'Hello world!');

        $this->assertInstanceOf(Message::class, $message);
        $this->assertEquals('Hello world!', $message->body);
        $this->assertEquals('text', $message->type->value);
        $this->assertFalse($message->is_deleted);
    }

    public function test_can_edit_message(): void
    {
        $message = $this->sendAs($this->user, 'Original');
        $edited = $this->messageService->edit($message, 'Edited body', $this->user->id);

        $this->assertEquals('Edited body', $edited->body);
        $this->assertTrue($edited->is_edited);
    }

    public function test_cannot_edit_another_users_message(): void
    {
        $otherUser = User::factory()->create();
        $message = $this->sendAs($this->user, 'Original');

        $this->expectException(ValidationException::class);

        $this->messageService->edit($message, 'Hacked body', $otherUser->id);

        $this->assertEquals('Original', $message->fresh()->body);
    }

    public function test_can_soft_delete_message(): void
    {
        $message = $this->sendAs($this->user, 'Will be deleted');
        $this->messageService->delete($message, $this->user->id);

        $deleted = $message->fresh();

        $this->assertTrue($deleted->is_deleted);
        $this->assertEquals($this->user->id, $deleted->deleted_by_user_id);
    }

    public function test_stranger_cannot_delete_message(): void
    {
        $stranger = User::factory()->create();
        // stranger has no membership in the room at all
        $message = $this->sendAs($this->user, 'Will not be deleted');

        $this->expectException(ValidationException::class);

        $this->messageService->delete($message, $stranger->id);

        $this->assertFalse($message->fresh()->is_deleted);
    }

    public function test_regular_member_cannot_delete_others_message(): void
    {
        $otherMember = User::factory()->create();
        Member::create([
            'room_id' => $this->room->id,
            'user_id' => $otherMember->id,
            'role' => 'member',
            'xp_points' => 0,
            'is_banned' => false,
            'joined_at' => now(),
            'last_read_at' => now(),
        ]);

        $message = $this->sendAs($this->user, 'Will not be deleted');

        $this->expectException(ValidationException::class);

        $this->messageService->delete($message, $otherMember->id);

        $this->assertFalse($message->fresh()->is_deleted);
    }

    public function test_moderator_can_delete_others_message(): void
    {
        $moderator = User::factory()->create();
        Member::create([
            'room_id' => $this->room->id,
            'user_id' => $moderator->id,
            'role' => 'moderator',
            'xp_points' => 0,
            'is_banned' => false,
            'joined_at' => now(),
            'last_read_at' => now(),
        ]);

        $message = $this->sendAs($this->user, 'Will be deleted by mod');

        $this->messageService->delete($message, $moderator->id);

        $this->assertTrue($message->fresh()->is_deleted);
        $this->assertEquals($moderator->id, $message->fresh()->deleted_by_user_id);
    }
}
