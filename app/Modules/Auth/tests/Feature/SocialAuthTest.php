<?php

namespace App\Modules\Auth\tests\Feature;

use App\Modules\Auth\Models\User;
use App\Modules\Auth\Models\UserSocialAccount;
use App\Modules\Auth\Notifications\VerifyEmailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class SocialAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.google' => ['client_id' => 'id', 'client_secret' => 'secret', 'redirect' => 'http://localhost/cb']]);
    }

    /** Siapkan Socialite agar mengembalikan user provider tertentu pada callback. */
    private function fakeProviderUser(array $attrs = [], array $raw = ['email_verified' => true], string $provider = 'google'): void
    {
        $attrs = array_merge([
            'id' => 'g-1',
            'nickname' => null,
            'name' => 'Budi Santoso',
            'email' => 'budi@example.com',
            'avatar' => null,
        ], $attrs);

        $socialUser = (new SocialiteUser)->setRaw($raw)->map($attrs);
        $socialUser->setToken('plain-access-token')->setRefreshToken('plain-refresh-token')->setExpiresIn(3600);

        $driver = Mockery::mock(Provider::class);
        $driver->shouldReceive('user')->andReturn($socialUser);

        Socialite::shouldReceive('driver')->with($provider)->andReturn($driver);
    }

    private function requestSocialCallback(string $provider = 'google')
    {
        return $this->get("/api/auth/{$provider}/callback");
    }

    // ── Provider ──

    public function test_unconfigured_provider_gets_404_on_redirect_and_callback(): void
    {
        $this->getJson('/api/auth/github/redirect')->assertNotFound();
        $this->get('/api/auth/github/callback')->assertNotFound();
        $this->getJson('/api/auth/tidak-dikenal/redirect')->assertNotFound();
        $this->getJson('/api/auth/slack/redirect')->assertNotFound();
    }

    public function test_redirect_returns_the_provider_url_for_a_configured_provider(): void
    {
        $driver = Mockery::mock(Provider::class);
        $driver->shouldReceive('redirect')->andReturn(new RedirectResponse('https://accounts.example/auth?state=abc'));
        Socialite::shouldReceive('driver')->with('google')->andReturn($driver);

        $this->getJson('/api/auth/google/redirect')
            ->assertOk()
            ->assertJsonPath('redirect_url', 'https://accounts.example/auth?state=abc');
    }

    // ── Login memakai sesi cookie, sama dengan email ──

    public function test_successful_social_login_creates_a_session_and_no_bearer_token(): void
    {
        Notification::fake();
        $this->fakeProviderUser();

        $this->requestSocialCallback()->assertRedirect('/');

        $user = User::where('email', 'budi@example.com')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertNotNull($user->last_login_at);
    }

    public function test_new_user_with_provider_verified_email_is_verified_and_not_emailed(): void
    {
        Notification::fake();
        $this->fakeProviderUser();

        $this->requestSocialCallback();

        $user = User::where('email', 'budi@example.com')->firstOrFail();
        $this->assertNotNull($user->email_verified_at);
        $this->assertNull($user->password);
        $this->assertDatabaseHas('user_social_accounts', ['user_id' => $user->id, 'provider' => 'google', 'provider_id' => 'g-1']);
        Notification::assertNothingSent();
    }

    public function test_new_user_with_unverified_provider_email_is_created_unverified_and_emailed(): void
    {
        Notification::fake();
        $this->fakeProviderUser(raw: ['email_verified' => false]);

        $this->requestSocialCallback()->assertRedirect('/');

        $user = User::where('email', 'budi@example.com')->firstOrFail();
        $this->assertNull($user->email_verified_at);
        Notification::assertSentTo($user, VerifyEmailNotification::class);
    }

    public function test_returning_social_user_logs_into_the_same_account(): void
    {
        $user = User::factory()->create(['email' => 'budi@example.com']);
        UserSocialAccount::create(['user_id' => $user->id, 'provider' => 'google', 'provider_id' => 'g-1', 'access_token' => 'x']);
        $this->fakeProviderUser();

        $this->requestSocialCallback()->assertRedirect('/');

        $this->assertAuthenticatedAs($user);
        $this->assertSame(1, User::count());
        $this->assertSame(1, UserSocialAccount::count());
    }

    // ── Penautan ke akun yang sudah ada ──

    public function test_links_to_existing_account_only_when_both_emails_are_verified(): void
    {
        $existing = User::factory()->create(['email' => 'budi@example.com']);
        $this->fakeProviderUser();

        $this->requestSocialCallback()->assertRedirect('/');

        $this->assertAuthenticatedAs($existing);
        $this->assertSame(1, User::count());
        $this->assertDatabaseHas('user_social_accounts', ['user_id' => $existing->id, 'provider' => 'google']);
    }

    public function test_refuses_to_link_when_provider_email_is_unverified(): void
    {
        $victim = User::factory()->create(['email' => 'budi@example.com']);
        $this->fakeProviderUser(raw: ['email_verified' => false]);

        $this->requestSocialCallback()->assertRedirect('/login?social_error=email_in_use');

        $this->assertGuest();
        $this->assertDatabaseMissing('user_social_accounts', ['user_id' => $victim->id]);
    }

    public function test_refuses_to_merge_into_an_unverified_local_account(): void
    {
        // Penyerang mendaftar lokal lebih dulu dengan email korban, tanpa verifikasi.
        $squatter = User::factory()->unverified()->create(['email' => 'budi@example.com']);
        $this->fakeProviderUser();

        $this->requestSocialCallback()->assertRedirect('/login?social_error=email_in_use');

        $this->assertGuest();
        $this->assertDatabaseMissing('user_social_accounts', ['user_id' => $squatter->id]);
    }

    // ── Status banned ──

    public function test_banned_user_with_linked_social_account_cannot_log_in(): void
    {
        $banned = User::factory()->create(['email' => 'budi@example.com', 'status' => 'banned']);
        UserSocialAccount::create(['user_id' => $banned->id, 'provider' => 'google', 'provider_id' => 'g-1', 'access_token' => 'x']);
        $this->fakeProviderUser();

        $this->requestSocialCallback()->assertRedirect('/login?social_error=banned');

        $this->assertGuest();
    }

    public function test_banned_user_cannot_log_in_through_email_linking(): void
    {
        $banned = User::factory()->create(['email' => 'budi@example.com', 'status' => 'banned']);
        $this->fakeProviderUser();

        $this->requestSocialCallback()->assertRedirect('/login?social_error=banned');

        $this->assertGuest();
        $this->assertDatabaseMissing('user_social_accounts', ['user_id' => $banned->id]);
    }

    // ── Kegagalan provider / state ──

    public function test_invalid_oauth_state_is_rejected(): void
    {
        $driver = Mockery::mock(Provider::class);
        $driver->shouldReceive('user')->andThrow(new InvalidStateException);
        Socialite::shouldReceive('driver')->with('google')->andReturn($driver);

        $this->requestSocialCallback()->assertRedirect('/login?social_error=invalid_state');

        $this->assertGuest();
    }

    public function test_provider_errors_redirect_to_login_instead_of_500(): void
    {
        $driver = Mockery::mock(Provider::class);
        $driver->shouldReceive('user')->andThrow(new \RuntimeException('provider down'));
        Socialite::shouldReceive('driver')->with('google')->andReturn($driver);

        $this->requestSocialCallback()->assertRedirect('/login?social_error=provider_error');

        $this->assertGuest();
    }

    // ── Penyimpanan ──

    public function test_provider_tokens_are_encrypted_at_rest(): void
    {
        $this->fakeProviderUser();
        $this->requestSocialCallback();

        $rawRow = DB::table('user_social_accounts')->first();
        $this->assertNotSame('plain-access-token', $rawRow->access_token);
        $this->assertNotSame('plain-refresh-token', $rawRow->refresh_token);

        $account = UserSocialAccount::first();
        $this->assertSame('plain-access-token', $account->access_token);
        $this->assertSame('plain-refresh-token', $account->refresh_token);
    }

    public function test_user_exposes_linked_social_accounts(): void
    {
        $user = User::factory()->create();
        UserSocialAccount::create(['user_id' => $user->id, 'provider' => 'google', 'provider_id' => 'g-9', 'access_token' => 'x']);
        UserSocialAccount::create(['user_id' => $user->id, 'provider' => 'github', 'provider_id' => 'gh-9', 'access_token' => 'y']);

        $this->assertEqualsCanonicalizing(['google', 'github'], $user->socialAccounts->pluck('provider')->all());
    }
}
