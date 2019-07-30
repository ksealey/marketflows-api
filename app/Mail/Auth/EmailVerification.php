<?php

namespace App\Mail\Auth;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Models\User;

class EmailVerification extends Mailable
{
    use Queueable, SerializesModels;

    protected $user;

    /** 
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(User $user)
    {
        $this->user = $user;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $this->user->createEmailVerification();

        return $this->view('mail.auth.email-verification', [
            'user' => $this->user
        ]);
    }
}
