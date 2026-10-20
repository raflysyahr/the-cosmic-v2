<?php

namespace App\Modules\Discuss\tests\Feature;

use App\Modules\Auth\Models\User;
use App\Modules\Discuss\Events\ContributionAwarded;
use App\Modules\Discuss\Http\Controllers\ToastDemoController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class ToastDemoTest extends TestCase
{
    use RefreshDatabase;

    private function pushRequest(User $user, array $payload): Request
    {
        $request = Request::create('/api/dev/toast-demo/push', 'POST', $payload);
        $request->setUserResolver(fn () => $user);

        return $request;
    }

    public function test_demo_routes_do_not_exist_outside_local(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/dev/toast-demo')->assertStatus(404);
        // Route fallback Laravel hanya menangkap GET/HEAD, jadi POST ke path
        // yang tidak terdaftar dijawab 405 (bukan 404). Intinya sama: route
        // demo tidak ada, dan request tidak pernah berhasil (bukan 2xx).
        $status = $this->actingAs($user)
            ->postJson('/api/dev/toast-demo/push', ['source' => 'message', 'amount' => 1])
            ->getStatusCode();
        $this->assertContains($status, [404, 405]);
    }

    public function test_controller_refuses_to_run_outside_local_even_if_called_directly(): void
    {
        $this->expectException(NotFoundHttpException::class);

        app(ToastDemoController::class)->push($this->pushRequest(User::factory()->create(), ['source' => 'message', 'amount' => 1]));
    }

    public function test_push_broadcasts_a_contribution_event_to_the_current_user_only(): void
    {
        Event::fake([ContributionAwarded::class]);
        $this->app['env'] = 'local';
        $user = User::factory()->create();

        $response = app(ToastDemoController::class)->push($this->pushRequest($user, [
            'source' => 'achievement', 'amount' => 20, 'detail' => 'First Reply',
        ]));

        $this->assertTrue($response->getData(true)['sent']);
        Event::assertDispatched(
            ContributionAwarded::class,
            fn ($e) => $e->userId === $user->id && $e->source === 'achievement' && $e->amount === 20 && $e->detail === 'First Reply' && $e->multiplierPct === 100,
        );
        $this->assertDatabaseCount('discuss_cp_logs', 0);
    }

    public function test_push_converts_multiplier_and_rejects_unknown_sources(): void
    {
        Event::fake([ContributionAwarded::class]);
        $this->app['env'] = 'local';
        $user = User::factory()->create();
        $controller = app(ToastDemoController::class);

        $controller->push($this->pushRequest($user, [
            'source' => 'message', 'amount' => 2, 'multiplier' => 2, 'event_name' => 'Weekend Boost',
        ]));
        Event::assertDispatched(
            ContributionAwarded::class,
            fn ($e) => $e->amount === 2 && $e->baseAmount === 1 && $e->multiplierPct === 200 && $e->eventName === 'Weekend Boost',
        );

        $this->expectException(ValidationException::class);
        $controller->push($this->pushRequest($user, ['source' => 'penalty', 'amount' => 5]));
    }
}
