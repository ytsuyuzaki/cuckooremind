<?php

namespace Tests\Feature;

use App\Models\Reminder;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_top_page_shows_current_team_summary(): void
    {
        $user = $this->createUserWithCurrentTeam();
        $otherTeam = Team::factory()->create();

        Reminder::query()->create([
            'team_id' => $user->currentTeam->id,
            'title' => 'Current reminder',
            'description' => 'Visible',
            'time' => '09:00:00',
            'to' => 'current@example.com',
            'type' => 'month:1',
            'compleded_at' => Carbon::create(2026, 4, 5, 9, 30, 0),
        ]);

        Reminder::query()->create([
            'team_id' => $user->currentTeam->id,
            'title' => 'Older reminder',
            'description' => 'Older',
            'time' => '08:00:00',
            'to' => 'older@example.com',
            'type' => 'month:2',
            'compleded_at' => Carbon::create(2026, 4, 4, 8, 0, 0),
        ]);

        Reminder::query()->create([
            'team_id' => $otherTeam->id,
            'title' => 'Other team reminder',
            'description' => 'Hidden',
            'time' => '10:00:00',
            'to' => 'other@example.com',
            'type' => 'month:3',
            'compleded_at' => Carbon::create(2026, 4, 6, 12, 0, 0),
        ]);

        $response = $this->actingAs($user)->get(route('top.index'));

        $response->assertOk()
            ->assertViewIs('top.index')
            ->assertViewHas('reminderCount', 2)
            ->assertViewHas('reminderLastTime', fn ($value) => $value?->equalTo(Carbon::create(2026, 4, 5, 9, 30, 0)));
    }

    public function test_settings_page_can_be_rendered(): void
    {
        $user = $this->createUserWithCurrentTeam();

        $this->actingAs($user)
            ->get(route('setting.edit'))
            ->assertOk()
            ->assertViewIs('setting.edit');
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
}
