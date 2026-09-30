<?php

namespace App\Modules\Auth\tests\Unit;

use App\Modules\Auth\Models\User;
use App\Modules\Auth\Models\UserProfile;
use App\Modules\Auth\Models\UserSocialAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * `user_social_accounts` only has a `created_at` column, and `user_profiles`
 * only has an `updated_at` column — both models set $timestamps = false
 * (correctly, since Eloquent's automatic timestamp support expects both
 * columns together). Without manual save hooks and explicit datetime casts,
 * these columns silently stayed NULL forever, or came back as raw strings
 * instead of Carbon instances. This test class guards against both.
 */
class ModelTimestampTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_social_account_created_at_is_populated(): void
    {
        $user = User::factory()->create();

        $account = UserSocialAccount::create([
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_id' => 'google-123',
            'access_token' => 'token',
        ]);

        $this->assertInstanceOf(Carbon::class, $account->fresh()->created_at);
    }

    public function test_user_profile_updated_at_is_populated_on_create(): void
    {
        $user = User::factory()->create();

        $profile = UserProfile::create([
            'user_id' => $user->id,
            'bio' => 'Hello world',
        ]);

        $this->assertInstanceOf(Carbon::class, $profile->fresh()->updated_at);
    }

    public function test_user_profile_updated_at_changes_on_update(): void
    {
        $user = User::factory()->create();

        $profile = UserProfile::create([
            'user_id' => $user->id,
            'bio' => 'Original bio',
        ]);
        $firstUpdatedAt = $profile->fresh()->updated_at;

        $this->travel(1)->hour();

        $profile->update(['bio' => 'Updated bio']);
        $secondUpdatedAt = $profile->fresh()->updated_at;

        $this->assertInstanceOf(Carbon::class, $secondUpdatedAt);
        $this->assertTrue($secondUpdatedAt->isAfter($firstUpdatedAt));
    }
}
