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
            ->where('joined', $user->created_at->format('Y-m-d'))
            ->where('emailVerified', true)
            ->missing('stats')
        );
    }

    public function test_profile_page_reports_unverified_email(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->get('/profile')
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page->where('emailVerified', false));
    }

    public function test_profile_page_has_null_profile_when_none_exists(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/profile')
            ->assertInertia(fn ($page) => $page->where('profile', null));
    }

    public function test_profile_fields_can_be_cleared_with_empty_strings(): void
    {
        $user = User::factory()->create();
        UserProfile::create([
            'user_id' => $user->id,
            'bio' => 'Old bio',
            'website_url' => 'https://example.com',
            'location' => 'Somewhere',
        ]);

        $this->actingAs($user)
            ->postJson('/api/user/profile', [
                'display_name' => $user->display_name,
                'username' => $user->username,
                'bio' => '',
                'website_url' => '',
                'location' => '',
            ])
            ->assertOk();

        $profile = UserProfile::where('user_id', $user->id)->first();
        $this->assertNull($profile->bio);
        $this->assertNull($profile->website_url);
        $this->assertNull($profile->location);
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
