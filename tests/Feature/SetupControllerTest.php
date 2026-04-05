<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\WriteDotenvService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Laravel\Fortify\Contracts\RegisterResponse;
use Tests\TestCase;

class SetupControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_setup_screen_is_displayed_when_application_is_in_setup_mode(): void
    {
        config(['app.setup' => true]);

        $this->get(route('setup.create'))
            ->assertOk()
            ->assertViewIs('setup.create');
    }

    public function test_setup_screen_redirects_to_user_creation_when_setup_is_disabled(): void
    {
        config(['app.setup' => false]);

        $this->get(route('setup.create'))
            ->assertRedirect(route('setup.user.create'));
    }

    public function test_setup_store_updates_expected_environment_values(): void
    {
        config(['app.setup' => true]);

        $writer = $this->mock(WriteDotenvService::class);
        $writer->shouldReceive('setValue')->once()->with('APP_URL', 'https://cuckoo.test');
        $writer->shouldReceive('setValue')->once()->with('DB_DATABASE', base_path('storage/db.sqlite'));
        $writer->shouldReceive('setValue')->once()->with('SESSION_DRIVER', 'database');
        $writer->shouldReceive('setValue')->once()->with('MAIL_FROM_ADDRESS', 'cuckoo@cuckoo.test');
        $writer->shouldReceive('setValue')->once()->with('APP_SETUP', 'false');

        Artisan::shouldReceive('call')->once()->with('key:generate', ['--force' => true]);
        Artisan::shouldReceive('call')->once()->with('cache:clear');

        putenv('HTTP_HOST=cuckoo.test');
        putenv('HTTPS=on');

        $this->from(route('setup.create'))
            ->post(route('setup.store'))
            ->assertRedirect(route('setup.create'));

        putenv('HTTP_HOST');
        putenv('HTTPS');
    }

    public function test_user_create_screen_is_displayed_when_no_users_exist(): void
    {
        $this->get(route('setup.user.create'))
            ->assertOk()
            ->assertViewIs('setup.user.create');
    }

    public function test_user_create_redirects_to_login_when_a_user_already_exists(): void
    {
        User::factory()->create();

        $this->get(route('setup.user.create'))
            ->assertRedirect(route('login'));
    }

    public function test_user_store_creates_and_logs_in_the_first_user(): void
    {
        Event::fake();

        $user = User::factory()->make([
            'email' => 'test@example.com',
        ]);

        $creator = $this->mock(CreatesNewUsers::class);
        $creator->shouldReceive('create')
            ->once()
            ->withArgs(fn (array $data) => $data['email'] === 'test@example.com')
            ->andReturn($user);

        $guard = $this->mock(StatefulGuard::class);
        $guard->shouldReceive('login')->once()->with($user);

        $this->app->instance(RegisterResponse::class, new class implements RegisterResponse
        {
            public function toResponse($request)
            {
                return redirect('/registered');
            }
        });

        config(['fortify.lowercase_usernames' => true]);

        $this->post(route('setup.user.store'), [
            'name' => 'Test User',
            'email' => 'TEST@EXAMPLE.COM',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect('/registered');

        Event::assertDispatched(Registered::class, fn (Registered $event) => $event->user === $user);
    }
}
