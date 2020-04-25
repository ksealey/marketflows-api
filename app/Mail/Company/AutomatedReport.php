<?php

namespace App\Mail\Company;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class AutomatedReport extends Mailable
{
    use Queueable, SerializesModels;

    public $report;
    public $filePath;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($report, $filePath)
    {
        $this->report   = $report;
        $this->filePath = $filePath;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->view('mail.company.automated-report', [
            'report' => $this->report
        ])
        ->attach($this->filePath);
    }
}
