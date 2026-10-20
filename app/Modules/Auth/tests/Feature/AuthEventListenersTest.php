<?php

namespace App\Modules\Auth\tests\Feature;

use App\Modules\Auth\Models\User;
use App\Modules\Auth\Notifications\VerifyEmailNotification;
use App\Modules\Auth\Services\EmailVerificationService;
use App\Modules\Auth\Services\SocialAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AuthEventListenersTest extends TestCase
{
    use RefreshDatabase;

    private function registerPayload(array $override = []): array
    {
        return array_merge([
            'name' => 'John Doe',
            'username' => 'johndoe',
            'email' => 'john@example.com',
            'password' => 'password123',
        ], $override);
    }

    // ── UserRegistered → email verifikasi ──

    public function test_registration_sends_the_project_verification_notification(): void
    {
        Notification::fake();

        $this->postJson('/api/register', $this->registerPayload())->assertStatus(201);

        $user = User::where('email', 'john@example.com')->firstOrFail();
        $this->assertNull($user->email_verified_at);
        Notification::assertSentTo($user, VerifyEmailNotification::class);
    }

    public function test_registration_still_succeeds_when_the_email_cannot_be_sent(): void
    {
        $this->mock(EmailVerificationService::class, function ($mock) {
            $mock->shouldReceive('send')->andThrow(new \RuntimeException('SMTP down'));
        });

        $this->postJson('/api/register', $this->registerPayload())->assertStatus(201);

        $this->assertDatabaseHas('users', ['email' => 'john@example.com']);
    }

    public function test_social_signup_with_placeholder_email_gets_no_verification_email(): void
    {
        Notification::fake();

        $user = app(SocialAuthService::class)->handleCallback('google', [
            'id' => '12345',
            'name' => 'Tanpa Email',
            'email' => null,
        ]);

        $this->assertStringEndsWith('.oauth', $user->email);
        Notification::assertNothingSent();
    }

    public function test_social_signup_with_real_unverified_email_gets_verification_email(): void
    {
        Notification::fake();

        $user = app(SocialAuthService::class)->handleCallback('google', [
            'id' => '67890',
            'name' => 'Punya Email',
            'email' => 'punya@example.com',
        ]);

        Notification::assertSentTo($user, VerifyEmailNotification::class);
    }

    // ── UserLoggedIn → last_login_at ──

    public function test_login_updates_last_login_at(): void
    {
        $user = User::factory()->create(['password' => 'password123', 'last_login_at' => null]);

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password123'])->assertOk();

        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_failed_login_does_not_touch_last_login_at(): void
    {
        $user = User::factory()->create(['password' => 'password123', 'last_login_at' => null]);

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'salah'])->assertStatus(422);

        $this->assertNull($user->fresh()->last_login_at);
    }

    // ── Alur verifikasi email ──

    public function test_resend_verification_notification_for_unverified_user(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->postJson('/api/email/verification-notification')->assertOk();

        Notification::assertSentTo($user, VerifyEmailNotification::class);
    }

    public function test_resend_is_a_noop_for_verified_user(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/email/verification-notification')
            ->assertOk()
            ->assertJsonPath('message', 'Email already verified.');

        Notification::assertNothingSent();
    }

    public function test_signed_verification_link_marks_the_email_verified(): void
    {
        $user = User::factory()->unverified()->create();

        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ]);

        $this->actingAs($user)->getJson($url)->assertOk();

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_verification_link_with_wrong_hash_is_rejected(): void
    {
        $user = User::factory()->unverified()->create();

        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $user->id,
            'hash' => sha1('orang-lain@example.com'),
        ]);

        $this->actingAs($user)->getJson($url)->assertStatus(400);

        $this->assertNull($user->fresh()->email_verified_at);
    }
}
