<?php

namespace App\Mail\Errors;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Models\Account;
use App\Models\User;

class AccountBalanceLow extends Mailable
{
    use Queueable, SerializesModels;

    protected $account;

    protected $user;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(Account $account, User $user)
    {
        $this->account = $account;
        $this->user    = $user;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->view('mail.errors.account-balance-low')
                    ->with([
                        'account' => $this->account,
                        'user'    => $this->user
                    ]);
    }
}
