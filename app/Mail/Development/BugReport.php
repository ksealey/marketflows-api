<?php

namespace App\Mail\Development;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\User;
use App\Models\Development\BugReport as BugReportModel;

class BugReport extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $bugReport;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(User $user, BugReportModel $bugReport)
    {
        $this->user      = $user;
        $this->bugReport = $bugReport;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->view('mail.development.bug-report')->subject('New Bug Report');
    }
}
