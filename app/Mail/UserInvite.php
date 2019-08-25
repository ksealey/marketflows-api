<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Models\UserInvite as UserInviteEmail;

class UserInvite extends Mailable
{
    use Queueable, SerializesModels;

    protected $userInvite;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(UserInviteEmail $userInvite)
    {
        $this->userInvite = $userInvite;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->view('mail.auth.user-invite', [
            'userInvite' => $this->userInvite
        ]);
    }
}
