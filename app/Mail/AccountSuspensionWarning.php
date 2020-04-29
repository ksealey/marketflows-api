<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;
use \Carbon\Carbon;

class AccountSuspensionWarning extends Mailable
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
        $releaseNumbersAt = (new Carbon($this->account->suspended_at))->addDays(7);
        
        return $this->view('mail.account-suspension-warning', [
            'user'              => $this->user,
            'account'           => $this->account,
            'releaseNumbersAt'  => $releaseNumbersAt
        ]);
    }
}
