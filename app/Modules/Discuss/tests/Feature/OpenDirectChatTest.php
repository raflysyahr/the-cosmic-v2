<?php

namespace App\Modules\Discuss\tests\Feature;

use App\Modules\Auth\Models\User;
use App\Modules\Discuss\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpenDirectChatTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_open_direct_chat_with_another_user_by_username(): void
    {
        $user = User::factory()->create();
        $target = User::factory()->create(['username' => 'targetuser']);

        $response = $this->actingAs($user)->post('/direct/targetuser');

        $room = Room::where('context_type', 'direct')->first();

        $response->assertRedirect(route('discuss.room', ['slug' => $room->slug]));

        $this->assertDatabaseHas('discuss_members', [
            'room_id' => $room->id,
            'user_id' => $user->id,
        ]);
        $this->assertDatabaseHas('discuss_members', [
            'room_id' => $room->id,
            'user_id' => $target->id,
        ]);
    }

    public function test_opening_existing_direct_chat_reuses_same_room(): void
    {
        $user = User::factory()->create();
        $target = User::factory()->create(['username' => 'targetuser']);

        $this->actingAs($user)->post('/direct/targetuser');
        $this->actingAs($user)->post('/direct/targetuser');

        $this->assertEquals(1, Room::where('context_type', 'direct')->count());
    }

    public function test_cannot_open_direct_chat_with_self(): void
    {
        $user = User::factory()->create(['username' => 'myself']);

        $response = $this->actingAs($user)->post('/direct/myself');

        $response->assertStatus(400);
    }

    public function test_guest_cannot_open_direct_chat(): void
    {
        $target = User::factory()->create(['username' => 'targetuser']);

        $response = $this->post('/direct/targetuser');

        $response->assertRedirect('/login');
    }

    public function test_opening_chat_with_unknown_username_404s(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/direct/does-not-exist');

        $response->assertStatus(404);
    }
}
