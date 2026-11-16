<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('app:send')->everyMinute();
        $schedule->command('app:notify-update')->weeklyOn(Schedule::MONDAY, '9:00')->withoutOverlapping();
        $schedule->command('queue:work --stop-when-empty --queue=high,default --sleep=3 --tries=3 --max-time=50')->everyMinute();
        $schedule->command('cache:clear')->dailyAt('4:56');
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
