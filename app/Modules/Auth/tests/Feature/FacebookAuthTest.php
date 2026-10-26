<?php

namespace App\Modules\Auth\tests\Feature;

use App\Modules\Auth\Models\User;
use App\Modules\Auth\Models\UserSocialAccount;
use App\Modules\Auth\Notifications\VerifyEmailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

/**
 * Facebook tidak menyediakan penanda "email terverifikasi". Field `verified` di payload-nya
 * berarti akun Facebook-nya terverifikasi, BUKAN emailnya, jadi tidak boleh dipercaya untuk
 * menautkan ke akun lokal (celah pengambilalihan akun).
 */
class FacebookAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.facebook' => ['client_id' => 'id', 'client_secret' => 'secret', 'redirect' => 'http://localhost/cb']]);
    }

    private function fakeFacebookUser(array $attrs = [], array $raw = ['verified' => true]): void
    {
        $attrs = array_merge([
            'id' => 'fb-1',
            'nickname' => null,
            'name' => 'Siti Aminah',
            'email' => 'siti@example.com',
            'avatar' => null,
        ], $attrs);

        $socialUser = (new SocialiteUser)->setRaw($raw)->map($attrs);
        $socialUser->setToken('fb-access-token')->setExpiresIn(3600);

        $driver = Mockery::mock(Provider::class);
        $driver->shouldReceive('user')->andReturn($socialUser);

        Socialite::shouldReceive('driver')->with('facebook')->andReturn($driver);
    }

    public function test_facebook_is_not_served_when_it_is_not_configured(): void
    {
        config(['services.facebook.client_id' => null]);

        $this->getJson('/api/auth/facebook/redirect')->assertNotFound();
        $this->get('/api/auth/facebook/callback')->assertNotFound();
    }

    public function test_new_facebook_user_is_logged_in_but_email_stays_unverified_and_is_emailed(): void
    {
        Notification::fake();
        $this->fakeFacebookUser();

        $this->get('/api/auth/facebook/callback')->assertRedirect('/');

        $user = User::where('email', 'siti@example.com')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertNull($user->email_verified_at);
        $this->assertDatabaseHas('user_social_accounts', ['user_id' => $user->id, 'provider' => 'facebook', 'provider_id' => 'fb-1']);
        Notification::assertSentTo($user, VerifyEmailNotification::class);
    }

    public function test_facebook_never_links_to_a_verified_local_account_even_if_its_account_is_verified(): void
    {
        $victim = User::factory()->create(['email' => 'siti@example.com']);
        $this->fakeFacebookUser(raw: ['verified' => true]);

        $this->get('/api/auth/facebook/callback')->assertRedirect('/login?social_error=email_in_use');

        $this->assertGuest();
        $this->assertDatabaseMissing('user_social_accounts', ['user_id' => $victim->id]);
    }

    public function test_facebook_account_without_email_gets_a_placeholder_email(): void
    {
        Notification::fake();
        $this->fakeFacebookUser(['email' => null]);

        $this->get('/api/auth/facebook/callback')->assertRedirect('/');

        $user = User::where('email', 'fb-1@facebook.oauth')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        Notification::assertNothingSent();
    }

    public function test_returning_facebook_user_logs_into_the_same_account(): void
    {
        $user = User::factory()->create(['email' => 'siti@example.com']);
        UserSocialAccount::create(['user_id' => $user->id, 'provider' => 'facebook', 'provider_id' => 'fb-1', 'access_token' => 'x']);
        $this->fakeFacebookUser();

        $this->get('/api/auth/facebook/callback')->assertRedirect('/');

        $this->assertAuthenticatedAs($user);
        $this->assertSame(1, User::count());
        $this->assertSame(1, UserSocialAccount::count());
    }
}
