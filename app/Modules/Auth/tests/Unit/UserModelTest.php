<?php

namespace App\Modules\Auth\tests\Unit;

use App\Modules\Auth\Enums\UserRole;
use App\Modules\Auth\Enums\UserStatus;
use App\Modules\Auth\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_has_ulid_primary_key(): void
    {
        $user = User::create([
            'username' => 'testuser',
            'email' => 'test@example.com',
            'display_name' => 'Test User',
            'password' => 'password',
        ]);

        $this->assertNotNull($user->id);
        $this->assertMatchesRegularExpression('/^[0-7][0-9a-hjkmnp-tv-z]{25}$/', $user->id);
    }

    public function test_casts_role_to_enum(): void
    {
        $user = User::create([
            'username' => 'testuser',
            'email' => 'test@example.com',
            'display_name' => 'Test User',
            'password' => 'password',
            'role' => 'admin',
        ]);

        $this->assertInstanceOf(UserRole::class, $user->role);
        $this->assertEquals(UserRole::Admin, $user->role);
    }

    public function test_casts_status_to_enum(): void
    {
        $user = User::create([
            'username' => 'testuser',
            'email' => 'test@example.com',
            'display_name' => 'Test User',
            'password' => 'password',
        ]);

        $fresh = $user->fresh();

        $this->assertInstanceOf(UserStatus::class, $fresh->status);
        $this->assertEquals(UserStatus::Active, $fresh->status);
    }

    public function test_avatar_url_returns_default_when_null(): void
    {
        $user = User::create([
            'username' => 'testuser',
            'email' => 'test@example.com',
            'display_name' => 'Test User',
            'password' => 'password',
            'avatar_url' => null,
        ]);

        $this->assertStringContainsString('Test+User', $user->avatar_url);
    }

    public function test_avatar_url_returns_custom_when_set(): void
    {
        $user = User::create([
            'username' => 'testuser',
            'email' => 'test@example.com',
            'display_name' => 'Test User',
            'password' => 'password',
            'avatar_url' => 'https://example.com/avatar.png',
        ]);

        $this->assertEquals('https://example.com/avatar.png', $user->avatar_url);
    }

    public function test_hidden_attributes(): void
    {
        $user = User::create([
            'username' => 'testuser',
            'email' => 'test@example.com',
            'display_name' => 'Test User',
            'password' => 'secret',
        ]);

        $json = $user->toArray();

        $this->assertArrayNotHasKey('password', $json);
        $this->assertArrayNotHasKey('remember_token', $json);
    }
}
