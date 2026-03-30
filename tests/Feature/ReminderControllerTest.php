<?php

namespace Tests\Feature;

use App\Models\Reminder;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ReminderControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_displays_only_current_team_reminders(): void
    {
        $user = $this->createUserWithCurrentTeam();

        $visibleReminder = $this->createReminder($user->currentTeam, [
            'title' => 'Visible reminder',
        ]);
        $hiddenReminder = $this->createReminder(Team::factory()->create(), [
            'title' => 'Hidden reminder',
        ]);

        $response = $this->actingAs($user)->get(route('reminders.index'));

        $response->assertOk();
        $response->assertViewHas('reminders', function ($reminders) use ($visibleReminder, $hiddenReminder) {
            $items = collect($reminders->items());

            return $items->contains('id', $visibleReminder->id)
                && ! $items->contains('id', $hiddenReminder->id);
        });
    }

    public function test_index_can_filter_reminders_by_search_word(): void
    {
        $user = $this->createUserWithCurrentTeam();

        $matchedReminder = $this->createReminder($user->currentTeam, [
            'title' => 'Buy milk',
            'description' => 'Remember the weekly grocery run',
        ]);
        $this->createReminder($user->currentTeam, [
            'title' => 'Morning walk',
            'description' => 'Take a short walk',
        ]);

        $response = $this->actingAs($user)->get(route('reminders.index', [
            'search' => 'milk',
        ]));

        $response->assertOk();
        $response->assertViewHas('reminders', function ($reminders) use ($matchedReminder) {
            $items = collect($reminders->items());

            return $items->count() === 1
                && $items->contains('id', $matchedReminder->id);
        });
    }

    public function test_create_screen_can_be_rendered(): void
    {
        $user = $this->createUserWithCurrentTeam();

        $response = $this->actingAs($user)->get(route('reminders.create'));

        $response->assertOk();
    }

    public function test_store_creates_monthly_reminder_for_current_team(): void
    {
        $user = $this->createUserWithCurrentTeam();

        $response = $this->actingAs($user)->post(route('reminders.store'), [
            'title' => 'Billing reminder',
            'description' => 'Pay the monthly bill',
            'time' => '08:30',
            'to' => 'notify@example.com',
            'type_mode' => 'month',
            'month' => '15',
        ]);

        $response->assertRedirect(route('reminders.index'));
        $response->assertSessionHas('success', '作成完了');
        $this->assertDatabaseHas('reminders', [
            'title' => 'Billing reminder',
            'type' => 'month:15',
            'team_id' => $user->currentTeam->id,
        ]);
    }

    public function test_store_creates_weekly_reminder_with_joined_weekdays(): void
    {
        $user = $this->createUserWithCurrentTeam();

        $this->actingAs($user)->post(route('reminders.store'), [
            'title' => 'Weekly sync',
            'description' => null,
            'time' => '09:00',
            'to' => 'team@example.com',
            'type_mode' => 'week',
            'week' => ['1', '3', '5'],
        ])->assertRedirect(route('reminders.index'));

        $this->assertDatabaseHas('reminders', [
            'title' => 'Weekly sync',
            'type' => 'week:1,3,5',
            'team_id' => $user->currentTeam->id,
        ]);
    }

    public function test_store_creates_one_time_reminder(): void
    {
        $user = $this->createUserWithCurrentTeam();

        $this->actingAs($user)->post(route('reminders.store'), [
            'title' => 'Appointment',
            'description' => 'Visit the clinic',
            'time' => '13:45',
            'to' => 'self@example.com',
            'type_mode' => 'once',
            'once' => '2026-04-15',
        ])->assertRedirect(route('reminders.index'));

        $this->assertDatabaseHas('reminders', [
            'title' => 'Appointment',
            'type' => 'once:2026-04-15',
            'team_id' => $user->currentTeam->id,
        ]);
    }

    public function test_show_allows_access_and_switches_current_team_when_user_belongs_to_reminders_team(): void
    {
        $user = $this->createUserWithCurrentTeam();
        $otherTeam = Team::factory()->create();
        $user->teams()->attach($otherTeam->id, ['role' => 'editor']);
        $reminder = $this->createReminder($otherTeam);

        $response = $this->actingAs($user)->get(route('reminders.show', $reminder));

        $response->assertOk();
        $response->assertViewHas('reminder', fn (Reminder $viewReminder) => $viewReminder->is($reminder));
        $this->assertSame($otherTeam->id, $user->fresh()->current_team_id);
    }

    public function test_show_returns_403_for_reminder_outside_users_teams(): void
    {
        $user = $this->createUserWithCurrentTeam();
        $reminder = $this->createReminder(Team::factory()->create());

        $this->actingAs($user)
            ->get(route('reminders.show', $reminder))
            ->assertForbidden();
    }

    public function test_edit_update_and_destroy_require_current_team_membership(): void
    {
        $user = $this->createUserWithCurrentTeam();
        $reminder = $this->createReminder(Team::factory()->create());

        $this->actingAs($user)->get(route('reminders.edit', $reminder))->assertForbidden();
        $this->actingAs($user)->put(route('reminders.update', $reminder), [
            'title' => 'Updated title',
            'description' => 'Updated description',
            'time' => '11:00',
            'to' => 'updated@example.com',
            'type_mode' => 'month',
            'month' => '20',
        ])->assertForbidden();
        $this->actingAs($user)->delete(route('reminders.destroy', $reminder))->assertForbidden();
    }

    public function test_edit_update_and_destroy_work_for_current_team_reminder(): void
    {
        $user = $this->createUserWithCurrentTeam();
        $reminder = $this->createReminder($user->currentTeam, [
            'title' => 'Original title',
            'type' => 'month:5',
        ]);

        $this->actingAs($user)
            ->get(route('reminders.edit', $reminder))
            ->assertOk()
            ->assertViewHas('reminder', fn (Reminder $viewReminder) => $viewReminder->is($reminder));

        $this->actingAs($user)->put(route('reminders.update', $reminder), [
            'title' => 'Updated title',
            'description' => 'Updated description',
            'time' => '11:00',
            'to' => 'updated@example.com',
            'type_mode' => 'week',
            'week' => ['2', '4'],
        ])->assertSessionHas('success', '更新完了');

        $this->assertDatabaseHas('reminders', [
            'id' => $reminder->id,
            'title' => 'Updated title',
            'type' => 'week:2,4',
            'to' => 'updated@example.com',
        ]);

        $this->actingAs($user)
            ->delete(route('reminders.destroy', $reminder))
            ->assertRedirect(route('reminders.index'))
            ->assertSessionHas('success', '削除が完了しました');

        $this->assertDatabaseMissing('reminders', [
            'id' => $reminder->id,
        ]);
    }

    public function test_export_returns_only_current_team_reminders_as_json(): void
    {
        $user = $this->createUserWithCurrentTeam();
        $currentReminder = $this->createReminder($user->currentTeam, [
            'title' => 'Current team reminder',
        ]);
        $this->createReminder(Team::factory()->create(), [
            'title' => 'Other team reminder',
        ]);

        $response = $this->actingAs($user)->get(route('reminders.export'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/force-download');
        $this->assertSame('attachment; filename="data.json"', $response->headers->get('Content-Disposition'));

        $json = json_decode($response->getContent(), true);

        $this->assertCount(1, $json);
        $this->assertSame($currentReminder->id, $json[0]['id']);
        $this->assertSame($user->currentTeam->id, $json[0]['team_id']);
    }

    public function test_import_creates_reminders_for_current_team_from_uploaded_json(): void
    {
        $user = $this->createUserWithCurrentTeam();
        $otherTeam = Team::factory()->create();

        $file = UploadedFile::fake()->createWithContent('reminders.json', json_encode([
            [
                'id' => 999,
                'title' => 'Imported reminder',
                'description' => 'Imported description',
                'time' => '07:15:00',
                'to' => 'import@example.com',
                'type' => 'once:2026-05-01',
                'compleded_at' => '2026-03-30 12:00:00',
                'team_id' => $otherTeam->id,
                'created_at' => '2026-03-30 12:00:00',
                'updated_at' => '2026-03-30 12:00:00',
            ],
        ], JSON_THROW_ON_ERROR));

        $this->actingAs($user)
            ->post(route('reminders.import'), ['json' => $file])
            ->assertSessionHas('success', 'インポートデータの登録が完了しました');

        $this->assertDatabaseHas('reminders', [
            'title' => 'Imported reminder',
            'team_id' => $user->currentTeam->id,
            'type' => 'once:2026-05-01',
        ]);

        $this->assertNull(
            Reminder::query()->where('title', 'Imported reminder')->firstOrFail()->compleded_at
        );
    }

    private function createUserWithCurrentTeam(): User
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->firstOrFail();

        $user->forceFill([
            'current_team_id' => $team->id,
        ])->save();

        return $user->fresh();
    }

    private function createReminder(Team $team, array $attributes = []): Reminder
    {
        return Reminder::query()->create(array_merge([
            'title' => 'Sample reminder',
            'description' => 'Sample description',
            'time' => '10:00:00',
            'to' => 'sample@example.com',
            'type' => 'month:1',
            'team_id' => $team->id,
        ], $attributes));
    }
}
