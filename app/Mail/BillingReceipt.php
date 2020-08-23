<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use \App\Models\User;
use \App\Models\BillingStatement;
use \App\Models\PaymentMethod;
use \App\Models\Payment;

class BillingReceipt extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $statement;
    public $paymentMethod;
    public $payment;
    public $statementUrl;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(User $user, BillingStatement $statement, PaymentMethod $paymentMethod, Payment $payment)
    {
        $this->user             = $user;
        $this->statement        = $statement;
        $this->paymentMethod    = $paymentMethod;
        $this->payment          = $payment;
        $this->statementUrl     = config('app.frontend_app_url') . '/statements/' . $this->statement->id;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->view('mail.billing-receipt')
                    ->subject('Billing receipt: #' . $this->statement->id);
    }
}
