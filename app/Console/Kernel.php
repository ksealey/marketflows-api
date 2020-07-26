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
        //  Send automated reports every 15 minutes
        $schedule->command('reports:dispatch-automations')
                 ->everyFifteenMinutes()
                 ->onOneServer();

        //  Create statements every 5 minutes
        $schedule->command('billing:create-statements')
                 ->everyFiveMinutes()
                 ->onOneServer();
       
        //  Bill accounts every 5 minutes
        $schedule->command('billing:bill-accounts')
                 ->everyFiveMinutes()
                 ->onOneServer();

         // Send suspension warnings every 15 minutes
         $schedule->command('accounts:suspension-warnings')
                  ->everyFifteenMinutes()
                  ->onOneServer();

         // Release suspended account numbers every 15 minutes
         $schedule->command('accounts:release-suspended-numbers')
                  ->everyFifteenMinutes()
                  ->onOneServer();

        $schedule->command('clear:password-resets')
                 ->hourly()
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
