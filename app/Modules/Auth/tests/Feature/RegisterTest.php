<?php

namespace App\Modules\Auth\tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_register_with_valid_data(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'John Doe',
            'username' => 'johndoe',
            'email' => 'john@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure(['message', 'user']);
    }

    public function test_cannot_register_with_duplicate_email(): void
    {
        $this->postJson('/api/register', [
            'name' => 'John Doe',
            'username' => 'johndoe',
            'email' => 'john@example.com',
            'password' => 'password123',
        ]);

        auth()->logout();
        $this->app->make('session')->invalidate();

        $response = $this->postJson('/api/register', [
            'name' => 'Jane Doe',
            'username' => 'janedoe',
            'email' => 'john@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    public function test_cannot_register_with_invalid_data(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => '',
            'username' => '',
            'email' => 'not-an-email',
            'password' => 'short',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name', 'username', 'email', 'password']);
    }
}
