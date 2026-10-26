<?php

namespace App\Modules\Auth\tests\Feature;

use App\Modules\Auth\Services\SocialAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SocialLoginProvidersTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_offers_google_when_it_is_configured(): void
    {
        config([
            'services.google' => ['client_id' => 'id', 'client_secret' => 'secret', 'redirect' => 'http://localhost/cb'],
            'services.facebook.client_id' => null,
        ]);

        $this->get('/login')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Login')
                ->where('socialProviders', ['google'])
            );
    }

    public function test_login_page_lists_google_and_facebook_in_order_when_both_are_configured(): void
    {
        config([
            'services.google' => ['client_id' => 'id', 'client_secret' => 'secret', 'redirect' => 'http://localhost/cb'],
            'services.facebook' => ['client_id' => 'id', 'client_secret' => 'secret', 'redirect' => 'http://localhost/cb'],
        ]);

        $this->get('/login')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('socialProviders', ['google', 'facebook']));
    }

    public function test_login_page_offers_only_facebook_when_google_is_not_configured(): void
    {
        config([
            'services.google.client_id' => null,
            'services.facebook' => ['client_id' => 'id', 'client_secret' => 'secret', 'redirect' => 'http://localhost/cb'],
        ]);

        $this->get('/login')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('socialProviders', ['facebook']));
    }

    public function test_login_page_hides_google_when_it_is_not_configured(): void
    {
        config([
            'services.google' => ['client_id' => null, 'client_secret' => null, 'redirect' => null],
            'services.facebook' => ['client_id' => null, 'client_secret' => null, 'redirect' => null],
        ]);

        $this->get('/login')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('socialProviders', []));
    }

    public function test_register_page_follows_the_same_provider_list(): void
    {
        config([
            'services.google' => ['client_id' => 'id', 'client_secret' => 'secret', 'redirect' => 'http://localhost/cb'],
            'services.facebook.client_id' => null,
        ]);
        $this->get('/register')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Register')->where('socialProviders', ['google']));

        config(['services.google.client_id' => '']);
        $this->get('/register')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('socialProviders', []));
    }

    public function test_only_providers_with_a_button_are_listed_even_if_others_are_configured(): void
    {
        config([
            'services.google' => ['client_id' => 'id'],
            'services.facebook.client_id' => null,
            'services.github' => ['client_id' => 'id'],
        ]);

        $this->assertSame(['google'], app(SocialAuthService::class)->enabledProviders());
    }

    public function test_username_never_starts_with_a_dash_when_the_name_has_no_letters(): void
    {
        $user = app(SocialAuthService::class)->handleCallback('google', [
            'id' => 'g-symbols',
            'name' => '!!!',
            'email' => 'symbols@example.com',
            'email_verified' => true,
        ]);

        $this->assertStringStartsWith('user-', $user->username);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]+$/', $user->username);
    }
}
