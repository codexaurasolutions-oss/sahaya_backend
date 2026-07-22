<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
     protected $commands = [
        \App\Console\Commands\AutoAttendanceCommand::class,
        \App\Console\Commands\GenerateMonthlySalary::class
    ];
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // $schedule->command('inspire')->hourly();
        $schedule->command('attendance:auto-mark')
            ->dailyAt('7:00')
            ->timezone('Asia/Kolkata') // Adjust to your timezone
            ->appendOutputTo(storage_path('logs/auto-attendance.log'));

        $schedule->command('salary:generate')
            ->dailyAt('6:00')
            ->timezone('Asia/Kolkata') // Adjust to your timezone
            ->appendOutputTo(storage_path('logs/auto-salary.log'));

        $schedule->command('subscriptions:generate-orders')
            ->everyMinute()
            ->timezone('Asia/Kolkata') // Adjust to your timezone
            ->appendOutputTo(storage_path('logs/auto-subscriptions.log'));

        $schedule->command('subscriptions:reset-user-limit')
            ->monthlyOn(1, '00:00')
            ->timezone('Asia/Kolkata') // Adjust to your timezone
            ->appendOutputTo(storage_path('logs/auto-subscriptions.log'));

        $schedule->command('subscriptions:expire')
            ->daily()
            ->timezone('Asia/Kolkata')
            ->appendOutputTo(storage_path('logs/auto-subscriptions.log'));

    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
