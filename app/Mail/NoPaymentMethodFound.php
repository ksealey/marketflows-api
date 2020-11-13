<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\User;

class NoPaymentMethodFound extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $paymentMethodsUrl;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(User $user)
    {
        $this->user               = $user;
        $this->paymentMethodsUrl  = config('app.frontend_app_url') . '/billing/payment-methods?a=create';
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->view('mail.no-payment-method-found');
    }
}
