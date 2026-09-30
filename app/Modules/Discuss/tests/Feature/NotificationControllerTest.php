<?php

namespace Tests\Feature\Discuss;

use App\Modules\Discuss\Models\Notification;
use App\Modules\Auth\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_mark_own_notification_as_read(): void
    {
        $user = User::factory()->create();
        $notification = Notification::create([
            'user_id' => $user->id,
            'type' => 'App\\Modules\\Discuss\\Events\\MessageSent',
            'data' => ['message' => 'test'],
            'is_read' => false,
        ]);

        $response = $this->actingAs($user)
            ->putJson("/api/notifications/{$notification->id}");

        $response->assertOk();
        $this->assertDatabaseHas('discuss_notifications', [
            'id' => $notification->id,
            'is_read' => true,
        ]);
    }

    public function test_user_cannot_mark_other_user_notification_as_read(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $notification = Notification::create([
            'user_id' => $otherUser->id,
            'type' => 'App\\Modules\\Discuss\\Events\\MessageSent',
            'data' => ['message' => 'test'],
            'is_read' => false,
        ]);

        $response = $this->actingAs($user)
            ->putJson("/api/notifications/{$notification->id}");

        $response->assertNotFound();
        $this->assertDatabaseHas('discuss_notifications', [
            'id' => $notification->id,
            'is_read' => false,
        ]);
    }

    public function test_guest_cannot_mark_notification_as_read(): void
    {
        $user = User::factory()->create();
        $notification = Notification::create([
            'user_id' => $user->id,
            'type' => 'App\\Modules\\Discuss\\Events\\MessageSent',
            'data' => ['message' => 'test'],
            'is_read' => false,
        ]);

        $response = $this->putJson("/api/notifications/{$notification->id}");

        $response->assertUnauthorized();
    }
}
