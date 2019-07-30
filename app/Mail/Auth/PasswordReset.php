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
        return $this->view('mail.auth.password-reset', [
            'user'  => $this->user,
            'reset' => $this->passwordReset
        ]);
    }
}
