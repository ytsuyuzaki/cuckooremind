<?php

namespace App\Notifications;

use App\Models\Reminder;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SendMail extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        protected Reminder $reminder,
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $title = $this->reminder->title;
        $desc = str_replace(["\r\n", "\r", "\n"], "\n", $this->reminder->description);
        $descs = explode("\n", $desc);

        $message = (new MailMessage)
            ->subject($title)
            ->replyTo($this->reminder->to)
            ->greeting($title)
            ->lines($descs)
            ->action('通知詳細', route('reminders.show', [$this->reminder]))
            ->salutation(implode(
                [
                    '登録内容からリマインドメールを送信しました。',
                    '解除したい場合には詳細から変更してください。',
                    config('app.name'),
                ]
            ));
        $message->viewData = [
            'nothingHeader' => true,
        ];

        return $message;
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [];
    }
}
