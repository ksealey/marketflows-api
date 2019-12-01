<?php

namespace App\Mail\Auth;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class PasswordReset extends Mailable
{
    use Queueable, SerializesModels;

    protected $user;

    protected $passwordReset;

    public $tries = 3;

    public $retryAfter = 5;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($user, $passwordReset)
    {
        $this->user           = $user;

        $this->passwordReset  = $passwordReset;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->view('mail.auth.password-reset')
                    ->with([
                        'user'      => $this->user,
                        'resetURL'  => trim(env('CLIENT_URL'), '/') 
                                        . '/auth/reset-password/u/' 
                                        . $this->user->id 
                                        . '/k/' 
                                        . $this->passwordReset->key
                    ]);
    }
}
