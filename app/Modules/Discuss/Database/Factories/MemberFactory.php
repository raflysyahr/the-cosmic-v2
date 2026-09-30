<?php

namespace App\Modules\Discuss\Database\Factories;

use App\Modules\Discuss\Models\Member;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

class MemberFactory extends Factory
{
    protected $model = Member::class;

    public function definition(): array
    {
        return [
            'room_id' => RoomFactory::new(),
            'user_id' => UserFactory::new(),
            'role' => 'member',
            'rank_id' => null,
            'xp_points' => 0,
            'muted_until' => null,
            'is_banned' => false,
            'last_read_at' => now(),
            'joined_at' => now(),
        ];
    }
}
