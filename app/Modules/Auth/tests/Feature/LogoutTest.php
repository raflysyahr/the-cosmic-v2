<?php

namespace App\Modules\Auth\tests\Feature;

use App\Modules\Auth\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class LogoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_logged_in_user_can_logout(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('password123'),
        ]);

        $login = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);
        $login->assertStatus(200);

        $this->getJson('/api/user')->assertStatus(200);

        $logout = $this->postJson('/api/logout');

        // The core regression this guards against: LogoutController used to
        // call auth()->logout() which, under the `auth:sanctum` middleware,
        // resolves to a RequestGuard that has no logout() method at all —
        // every real logout attempt fatally errored (500), regardless of
        // this test's ability to simulate cross-request cookie behavior.
        $logout->assertStatus(200);

        // Confirm the actual session guard used by LoginController (`web`)
        // is genuinely cleared, not just that the endpoint didn't crash.
        $this->assertFalse(Auth::guard('web')->check());
        $this->assertNull(Auth::guard('web')->id());
    }

    public function test_guest_cannot_logout(): void
    {
        $response = $this->postJson('/api/logout');

        $response->assertStatus(401);
    }
}
