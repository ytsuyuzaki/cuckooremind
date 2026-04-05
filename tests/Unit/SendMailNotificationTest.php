<?php

namespace Tests\Unit;

use App\Models\Reminder;
use App\Models\Team;
use App\Notifications\SendMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Tests\TestCase;

class SendMailNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_mail_notification_contains_reminder_content_and_links(): void
    {
        $team = Team::factory()->create();
        $reminder = Reminder::query()->create([
            'team_id' => $team->id,
            'title' => '朝のリマインド',
            'description' => "1行目\r\n2行目",
            'time' => '10:00:00',
            'to' => 'notify@example.com',
            'type' => 'once:2026-04-05',
        ]);

        $mail = (new SendMail($reminder))->toMail(new AnonymousNotifiable);

        $this->assertSame('朝のリマインド', $mail->subject);
        $this->assertSame('朝のリマインド', $mail->greeting);
        $this->assertSame(['1行目', '2行目'], $mail->introLines);
        $this->assertSame('通知詳細', $mail->actionText);
        $this->assertSame(route('reminders.show', $reminder), $mail->actionUrl);
        $this->assertSame('notify@example.com', $mail->replyTo[0][0]);
        $this->assertTrue($mail->viewData['nothingHeader']);
        $this->assertStringContainsString('登録内容からリマインドメールを送信しました。', $mail->salutation);
        $this->assertStringContainsString(config('app.name'), $mail->salutation);
    }
}
