<?php

namespace App\Modules\Discuss\Database\Factories;

use App\Modules\Discuss\Models\Room;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class RoomFactory extends Factory
{
    protected $model = Room::class;

    public function definition(): array
    {
        $name = fake()->words(3, true);

        return [
            'slug' => Str::slug($name) . '-' . Str::random(4),
            'name' => $name,
            'description' => fake()->sentence(),
            'cover_url' => null,
            'type' => 'public',
            'owner_user_id' => UserFactory::new(),
            'context_type' => null,
            'context_id' => null,
            'is_active' => true,
            'settings' => [
                'slow_mode_seconds' => 0,
                'max_members' => 500,
                'xp_per_message' => 5,
            ],
        ];
    }
}
