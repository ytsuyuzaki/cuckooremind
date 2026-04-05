<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_cron_endpoint_runs_the_scheduler_and_returns_output(): void
    {
        Sanctum::actingAs(User::factory()->withPersonalTeam()->create());

        config(['app.php_binary' => '/custom/php']);

        Artisan::shouldReceive('call')->once()->with('schedule:run');
        Artisan::shouldReceive('output')->once()->andReturn('No scheduled commands are ready to run.');

        putenv('PHP_BINARY=/usr/bin/php');

        $this->getJson(route('cron'))
            ->assertOk()
            ->assertSeeText('No scheduled commands are ready to run.');

        $this->assertSame('/custom/php', getenv('PHP_BINARY'));

        putenv('PHP_BINARY');
    }
}
