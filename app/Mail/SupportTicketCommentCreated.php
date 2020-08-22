<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use \App\Models\SupportTicket;
use \App\Models\SupportTicketComment;

class SupportTicketCommentCreated extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $agent;
    public $supportTicket;
    public $supportTicketComment;
    
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($user, $agent, SupportTicket $supportTicket, SupportTicketComment $supportTicketComment)
    {
        $this->user                 = $user;
        $this->agent                = $agent;
        $this->supportTicket        = $supportTicket; 
        $this->supportTicketComment = $supportTicketComment; 
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->view('mail.support-ticket-comment-created');
    }
}
