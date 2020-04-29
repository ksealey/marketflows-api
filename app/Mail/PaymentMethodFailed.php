<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class PaymentMethodFailed extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $paymentMethod;
    public $statement;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($user, $paymentMethod, $statement)
    {
        $this->user = $user;
        $this->paymentMethod = $paymentMethod;
        $this->statement = $statement;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->view('mail.payment-method-failed', [
            'user' => $this->user,
            'paymentMethod' => $this->paymentMethod,
            'statement' => $this->statement
        ]);
    }
}
