<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ApplicationUpdateAvailable extends Notification
{
    public function __construct(public readonly string $version) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('CuckooRemind '.$this->version.' に更新できます')
            ->line('CuckooRemind '.$this->version.' が利用可能です。')
            ->action('システム更新を開く', route('system-updates.index'));
    }
}
