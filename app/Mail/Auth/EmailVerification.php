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

    public $user;

    public $verification;

    public $tries = 3;

    public $retryAfter = 5;

    /** 
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(User $user)
    {
        $this->user = $user;

        $this->verification = EmailVerificationRecord::create([
            'user_id'       => $this->user->id,  
            'key'           => str_random(40),
            'expires_at'    => date('Y-m-d H:i:s', strtotime('now +24 hours'))
        ]);
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->view('mail.auth.email-verification', [
            'user'          => $this->user,
            'verification'  => $this->verification
        ]);
    }
}
