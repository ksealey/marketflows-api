<?php

namespace App\Mail\Auth;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class PasswordReset extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $resetUrl;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($user)
    {
        $this->user     = $user;
        $this->resetUrl = config('app.frontend_app_url')
                        . '/reset-password?user_id='
                        . $user->id 
                        . '&token='
                        . $user->password_reset_token;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {

        return $this->view('mail.auth.password-reset')
                    ->subject('Reset Password');
    }
}
