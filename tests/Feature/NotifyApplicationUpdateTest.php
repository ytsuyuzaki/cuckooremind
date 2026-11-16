<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\ApplicationUpdateAvailable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NotifyApplicationUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_release_is_emailed_to_system_admins_only(): void
    {
        $admins = User::factory()->count(2)->systemAdmin()->create();
        $user = User::factory()->create();
        $version = $this->cuckooRemindVersionAfter();
        $this->fakeRelease($version);
        Notification::fake();

        $this->artisan('app:notify-update')->assertSuccessful();

        Notification::assertSentTo($admins, ApplicationUpdateAvailable::class,
            function (ApplicationUpdateAvailable $notification, array $channels, User $admin) use ($version): bool {
                $message = $notification->toMail($admin);

                return $channels === ['mail']
                    && str_contains($message->subject, $version)
                    && $message->actionUrl === route('system-updates.index');
            });
        Notification::assertNothingSentTo($user);
    }

    public function test_current_release_does_not_trigger_a_notification(): void
    {
        User::factory()->systemAdmin()->create();
        $this->fakeRelease(cuckooremind_version());
        Notification::fake();

        $this->artisan('app:notify-update')->assertSuccessful();

        Notification::assertNothingSent();
    }

    private function fakeRelease(string $version): void
    {
        config(['update.enabled' => true]);

        Http::fake(['*' => Http::response([[
            'tag_name' => $version,
            'draft' => false,
            'prerelease' => false,
        ]])]);
    }
}
