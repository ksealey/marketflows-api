<?php

namespace App\Mail\Auth;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Models\Auth\EmailVerification as EmailVerificationRecord;
use App\Models\User;

class EmailVerification extends Mailable
{
    use Queueable, SerializesModels;

    public $emailVerification;
    public $tries = 3;
    public $retryAfter = 5;

    /** 
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(EmailVerificationRecord $emailVerification)
    {
        $this->emailVerification = $emailVerification;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->to($this->emailVerification->email)
                    ->view('mail.auth.email-verification')
                    ->subject('Your verification code');
    }
}
