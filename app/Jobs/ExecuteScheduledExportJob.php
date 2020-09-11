<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use \App\Models\Company\ScheduledExport;
use \App\Mail\ScheduledExport as ScheduledExportMail;
use \Carbon\Carbon;
use Mail;

class ExecuteScheduledExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $scheduledExport;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(ScheduledExport $scheduledExport)
    {
        $this->scheduledExport = $scheduledExport;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $schedule = $this->scheduledExport;
        $report   = $schedule->report;
        
        $report->export(true);
        if( $schedule->delivery_method == 'email' ){
            foreach( $schedule->delivery_email_addresses as $email ){
                Mail::to($email)->send(new ScheduledExportMail($report));
            }
        }

        $schedule->last_export_at = now();
        $schedule->save();
    }
}
