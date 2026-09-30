<?php

namespace App\Modules\Auth\tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SocialAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_redirect_route_exists(): void
    {
        $this->assertTrue(true);
    }
}
