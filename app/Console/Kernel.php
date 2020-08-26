<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        //
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        //  Bill accounts every 5 minutes
         $schedule->command('bill-accounts')
                  ->everyFiveMinutes()
                  ->onOneServer();

        //  Push scheduled export jobs
        $schedule->command('push-scheduled-exports')
                 ->everyFiveMinutes()
                 ->onOneServer();

        //  Push account suspension jobs
        $schedule->command('send-account-suspension-warnings')
                 ->everyFiveMinutes()
                 ->onOneServer();
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
