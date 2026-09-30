<?php

namespace App\Modules\Discuss\Database\Factories;

use App\Modules\Discuss\Models\Message;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

class MessageFactory extends Factory
{
    protected $model = Message::class;

    public function definition(): array
    {
        return [
            'room_id' => RoomFactory::new(),
            'user_id' => UserFactory::new(),
            'reply_to_id' => null,
            'type' => 'text',
            'body' => fake()->sentence(),
            'attachments' => [],
            'is_edited' => false,
            'is_deleted' => false,
            'deleted_by_user_id' => null,
            'metadata' => [],
        ];
    }
}
