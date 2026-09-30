<?php

namespace App\Modules\Auth\tests\Feature;

use App\Modules\Auth\Models\User;
use App\Modules\Auth\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EditProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_view_own_profile_edit_page(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/profile');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Profile')
            ->has('stats')
            ->missing('stats.bookmarks')
        );
    }

    public function test_profile_edit_page_works_with_existing_profile_data(): void
    {
        $user = User::factory()->create();
        UserProfile::create([
            'user_id' => $user->id,
            'bio' => 'Test bio',
        ]);

        $response = $this->actingAs($user)->get('/profile');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->where('profile.bio', 'Test bio')
        );
    }

    public function test_guest_cannot_view_profile_edit_page(): void
    {
        $response = $this->get('/profile');

        $response->assertRedirect('/login');
    }
}
