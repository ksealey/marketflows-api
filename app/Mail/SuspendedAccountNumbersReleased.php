<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class SuspendedAccountNumbersReleased extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $account;
    public $phoneNumbers = [];

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($user, $account, $phoneNumbers)
    {
        $this->user         = $user;
        $this->account      = $account;
        $this->phoneNumbers = $phoneNumbers;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->view('mail.suspended-account-numbers-released', [
            'user' => $this->user,
            'account' => $this->account,
            'phoneNumbers' => $this->phoneNumbers
        ]);
    }
}
