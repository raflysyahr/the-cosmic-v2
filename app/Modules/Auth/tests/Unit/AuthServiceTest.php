<?php

namespace App\Modules\Auth\tests\Unit;

use App\Modules\Auth\Data\RegisterData;
use App\Modules\Auth\Models\User;
use App\Modules\Auth\Services\AuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthServiceTest extends TestCase
{
    use RefreshDatabase;

    private AuthService $authService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->authService = app(AuthService::class);
    }

    public function test_can_register_user(): void
    {
        $data = new RegisterData(
            name: 'John Doe',
            username: 'johndoe',
            email: 'john@example.com',
            password: 'password123',
        );

        $user = $this->authService->register($data);

        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals('johndoe', $user->username);
        $this->assertEquals('John Doe', $user->display_name);
        $this->assertEquals('reader', $user->role->value);
        $this->assertNotNull($user->id);
    }

    public function test_can_login_with_valid_credentials(): void
    {
        $data = new RegisterData(
            name: 'John Doe',
            username: 'johndoe',
            email: 'john@example.com',
            password: 'password123',
        );

        $this->authService->register($data);

        $user = $this->authService->login('john@example.com', 'password123');

        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals('johndoe', $user->username);
    }

    public function test_cannot_login_with_invalid_credentials(): void
    {
        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $this->authService->login('nonexistent@example.com', 'wrongpassword');
    }

    public function test_cannot_login_when_banned(): void
    {
        $data = new RegisterData(
            name: 'John Doe',
            username: 'johndoe',
            email: 'john@example.com',
            password: 'password123',
        );

        $user = $this->authService->register($data);
        $user->update(['status' => 'banned']);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $this->authService->login('john@example.com', 'password123');
    }
}
