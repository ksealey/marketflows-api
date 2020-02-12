<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use App\Models\PaymentMethod;

class PaymentMethodRule implements Rule
{
    protected $message = '';

    protected $accountId;

    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct($accountId)
    {
        $this->accountId = $accountId;
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        $paymentMethod = PaymentMethod::find($value);

        if( ! $paymentMethod || $paymentMethod->account_id !== $this->accountId ){
            $this->message = 'Payment method does not exist';

            return false;
        }

        return true;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return $this->message;
    }
}
