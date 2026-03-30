<?php

namespace Tests\Feature;

use App\Models\Reminder;
use App\Models\Team;
use App\Notifications\SendMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SendReminderMailTest extends TestCase
{
    use RefreshDatabase;

    public function test_due_reminder_is_sent_by_mail_and_marked_as_completed(): void
    {
        $this->travelTo(now()->create(2026, 3, 30, 10, 0, 34));

        Notification::fake();

        $team = Team::factory()->create();
        $reminder = Reminder::query()->create([
            'team_id' => $team->id,
            'title' => '朝のリマインド',
            'description' => "1行目\n2行目",
            'time' => '10:00',
            'to' => 'due@example.com',
            'type' => 'once:2026-03-30',
        ]);

        $this->artisan('app:send')->assertExitCode(0);

        Notification::assertSentOnDemand(SendMail::class, function (SendMail $notification, array $channels, AnonymousNotifiable $notifiable) use ($reminder) {
            $mail = $notification->toMail($notifiable);

            return $channels === ['mail']
                && $notifiable->routes['mail'] === $reminder->to
                && $mail->subject === $reminder->title
                && $mail->replyTo[0][0] === $reminder->to;
        });

        Notification::assertSentOnDemandTimes(SendMail::class, 1);

        $this->assertTrue($reminder->fresh()->compleded_at->equalTo(now()->copy()->second(0)));
    }

    public function test_non_due_and_already_completed_reminders_are_not_sent(): void
    {
        $this->travelTo(now()->create(2026, 3, 30, 10, 0, 34));

        Notification::fake();

        $team = Team::factory()->create();

        $futureReminder = Reminder::query()->create([
            'team_id' => $team->id,
            'title' => '未来のリマインド',
            'description' => 'まだ送らない',
            'time' => '10:00',
            'to' => 'future@example.com',
            'type' => 'once:2026-03-31',
        ]);

        $completedReminder = Reminder::query()->create([
            'team_id' => $team->id,
            'title' => '送信済みリマインド',
            'description' => '再送しない',
            'time' => '10:00',
            'to' => 'completed@example.com',
            'type' => 'once:2026-03-30',
            'compleded_at' => '2026-03-30 10:00:00',
        ]);

        $this->artisan('app:send')->assertExitCode(0);

        Notification::assertNothingSent();
        $this->assertNull($futureReminder->fresh()->compleded_at);
        $this->assertTrue($completedReminder->fresh()->compleded_at->equalTo(now()->copy()->second(0)));
    }
}
