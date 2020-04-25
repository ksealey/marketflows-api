<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Mail\Company\AutomatedReport;
use Mail;

class ExecuteReportAutomation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $automation;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($automation)
    {
        $this->automation = $automation;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $report   = $this->automation->report;
        $filePath = $report->export(true);

        if( $this->automation->type === 'EMAIL' ){
            foreach( $this->automation->email_addresses as $email ){
                Mail::to($email)->send(new AutomatedReport($report, $filePath));
            }
        }

        $this->automation->locked_since = null;
        $this->automation->last_ran_at  = now()->format('Y-m-d'); 
        $this->automation->save();

        unlink($filePath);
    }
}
