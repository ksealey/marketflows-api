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
    public $verificationUrl;
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

        EmailVerificationRecord::where('user_id', $this->user->id)
                               ->delete();

        $this->verification = EmailVerificationRecord::create([
            'user_id'       => $this->user->id,  
            'key'           => str_random(40),
            'expires_at'    => date('Y-m-d H:i:s', strtotime('now +24 hours'))
        ]);

        $this->verificationUrl = config('app.frontend_app_url') 
                                . '/verify-email?uid=' 
                                . $this->user->id 
                                . '&key=' 
                                . $this->verification->key;
        }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->view('mail.auth.email-verification')
                    ->subject('Verify your email address');
    }
}
