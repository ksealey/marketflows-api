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

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($creator, $user)
    {
        $this->creator = $creator;
        $this->user    = $user;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject($this->creator->full_name. ' has added you to their account on ' . env('APP_NAME') . '!')
                    ->view('mail.add-user', [
                        'creator' => $this->creator,
                        'user'    => $this->user,
                    ]);
    }
}
