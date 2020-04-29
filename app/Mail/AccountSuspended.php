<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class AccountSuspended extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $account;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($user, $account)
    {
        $this->user    = $user;
        $this->account = $account;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject('Your account has been suspended')->view('mail.account-suspended', [
            'user'    => $this->user,
            'account' => $this->account
        ]);
    }
}
