<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;
use Laravel\Jetstream\TeamInvitation as TeamInvitationModel;

class UserInvitation extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public TeamInvitationModel $invitation,
        public string $token,
    ) {}

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        // TODO: パスワードの設定 & メール認証 & チーム認証 の3つのURLにアクセスするのを一括にまとめたい
        return $this->markdown('emails.team-invitation', [
            'resetPasswordUrl' => URL::signedRoute('password.reset', [
                'token' => $this->token,
            ]),
            'acceptUrl' => URL::signedRoute('team-invitations.accept', [
                'invitation' => $this->invitation,
            ]),
        ])->subject(__('Team Invitation'));
    }
}
