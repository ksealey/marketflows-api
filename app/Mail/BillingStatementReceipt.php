<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class BillingStatementReceipt extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $statement;
    public $total;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($user, $statement, $total)
    {
        $this->user      = $user;
        $this->statement = $statement;
        $this->total     = $total;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $user = $this->user;
        $billingPeriodStart = new DateTime($this->statement->billing_period_starts_at);
        $billingPeriodEnd   = new DateTime($this->statement->billing_period_ends_at);
        
        return $this->view('mail.billing-statement-receipt', [
            'user'                  => $user,
            'billingPeriodStart'    => $billingPeriodStart->format('M j, Y'),
            'billingPeriodEnd'      => $billingPeriodEnd->format('M j, Y'),
            'statement'             => $this->statement,
            'total'                 => $this->total
        ]);
    }
}
