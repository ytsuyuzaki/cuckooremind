<?php

namespace Tests\Unit;

use App\Mail\UserInvitation;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserInvitationMailTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_invitation_builds_signed_urls_for_password_reset_and_acceptance(): void
    {
        $team = Team::factory()->create();
        $invitation = $team->teamInvitations()->create([
            'email' => 'invitee@example.com',
            'role' => 'admin',
        ]);

        $mail = (new UserInvitation($invitation, 'token-123'))->build();
        $viewData = $mail->buildViewData();

        $this->assertSame(__('Team Invitation'), $mail->subject);
        $this->assertSame('emails.team-invitation', $mail->markdown);
        $this->assertStringContainsString('token-123', $viewData['resetPasswordUrl']);
        $this->assertStringContainsString((string) $invitation->getKey(), $viewData['acceptUrl']);
        $this->assertStringContainsString('signature=', $viewData['resetPasswordUrl']);
        $this->assertStringContainsString('signature=', $viewData['acceptUrl']);
    }
}
