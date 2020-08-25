<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AddUser extends Mailable
{
    use Queueable, SerializesModels;

    public $creator;
    public $user;
    public $resetUrl;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($creator, $user)
    {
        $this->creator      = $creator;
        $this->user         = $user;
        $this->resetUrl     = config('app.frontend_app_url')
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
        return $this->subject($this->creator->full_name. ' has added you to their account')
                    ->view('mail.add-user');
    }
}
