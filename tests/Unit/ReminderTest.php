<?php

namespace Tests\Unit;

use App\Models\Reminder;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ReminderTest extends TestCase
{
    public function test_repeat_text_is_resolved_for_each_type(): void
    {
        $this->travelTo(Carbon::create(2026, 3, 30, 10, 0, 0));

        $this->assertSame('毎日', $this->makeReminder([
            'type' => 'day:1',
        ])->repeat_text);

        $this->assertSame('毎週月,水,金曜日', $this->makeReminder([
            'type' => 'week:1,3,5',
        ])->repeat_text);

        $this->assertSame('毎月15日', $this->makeReminder([
            'type' => 'month:15',
        ])->repeat_text);

        $this->assertSame('2026年04月01日', $this->makeReminder([
            'type' => 'once:2026-04-01',
        ])->repeat_text);
    }

    public function test_next_send_for_daily_reminder_is_today_when_time_has_not_passed_since_update(): void
    {
        $this->travelTo(Carbon::create(2026, 3, 30, 9, 0, 0));

        $reminder = $this->makeReminder([
            'time' => '10:15',
            'type' => 'day:1',
            'updated_at' => '2026-03-30 08:00:00',
        ]);

        $this->assertTrue($reminder->next_send->equalTo(Carbon::create(2026, 3, 30, 10, 15, 0)));
    }

    public function test_next_send_for_daily_reminder_moves_to_next_day_when_todays_time_has_already_passed(): void
    {
        $this->travelTo(Carbon::create(2026, 3, 30, 10, 30, 0));

        $reminder = $this->makeReminder([
            'time' => '10:15',
            'type' => 'day:1',
            'updated_at' => '2026-03-30 10:20:00',
        ]);

        $this->assertTrue($reminder->next_send->equalTo(Carbon::create(2026, 3, 31, 10, 15, 0)));
    }

    public function test_next_send_for_weekly_reminder_picks_the_nearest_scheduled_weekday(): void
    {
        $this->travelTo(Carbon::create(2026, 3, 30, 9, 0, 0));

        $reminder = $this->makeReminder([
            'time' => '10:00',
            'type' => 'week:1,3',
            'updated_at' => '2026-03-30 10:30:00',
        ]);

        $this->assertTrue($reminder->next_send->equalTo(Carbon::create(2026, 4, 1, 10, 0, 0)));
    }

    public function test_next_send_for_monthly_reminder_moves_to_next_month_when_current_month_has_passed(): void
    {
        $this->travelTo(Carbon::create(2026, 3, 30, 9, 0, 0));

        $reminder = $this->makeReminder([
            'time' => '10:00',
            'type' => 'month:15',
            'updated_at' => '2026-03-16 00:00:00',
        ]);

        $this->assertTrue($reminder->next_send->equalTo(Carbon::create(2026, 4, 15, 10, 0, 0)));
    }

    public function test_next_send_for_once_reminder_returns_null_after_completion(): void
    {
        $this->travelTo(Carbon::create(2026, 3, 30, 9, 0, 0));

        $reminder = $this->makeReminder([
            'time' => '10:00',
            'type' => 'once:2026-03-30',
            'compleded_at' => '2026-03-30 10:00:00',
        ]);

        $this->assertNull($reminder->next_send);
    }

    private function makeReminder(array $attributes): Reminder
    {
        $reminder = new Reminder;
        $reminder->setRawAttributes(array_merge([
            'title' => 'Test Reminder',
            'description' => 'Description',
            'time' => '10:00',
            'to' => 'test@example.com',
            'type' => 'day:1',
            'compleded_at' => null,
            'updated_at' => '2026-03-30 08:00:00',
            'created_at' => '2026-03-30 08:00:00',
        ], $attributes), true);

        return $reminder;
    }
}
