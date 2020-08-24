<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use \App\Models\SupportTicket;

class SupportTicketCreated extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $supportTicket;
    

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($user, SupportTicket $supportTicket)
    {
        $this->user          = $user;
        $this->supportTicket = $supportTicket; 
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->view('mail.support-ticket-created')
                    ->subject('Support ticket #' . $this->supportTicket->id . ' created');
    }
}
