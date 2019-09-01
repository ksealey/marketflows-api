<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use \App\Models\PaymentMethod;
use \App\Models\Charge;
use \App\Models\ChargeError;

class PaymentMethodTest extends TestCase
{
    use \Tests\CreatesUser;

    /**
     * Test creating a payment method
     *
     * @group payment-methods
     * @return void
     */
    public function testPaymentMethodFunctions()
    {
        $stripeToken  = 'tok_visa';
        $declineToken = 'tok_chargeCustomerFail';
        $user        = $this->createUser();

        //  Test creating a payment method
        $paymentMethod = PaymentMethod::createFromToken($stripeToken, $user);
        $this->assertTrue($user->account->external_id != null);
        $this->assertTrue($paymentMethod != null);
        $this->assertTrue($paymentMethod->created_by == $user->id);
        $this->assertTrue($paymentMethod->hasRemoteResource() == true);

        //  Test creating a second card as primary
        $paymentMethod2 = PaymentMethod::createFromToken($declineToken, $user, true);
        $this->assertTrue($paymentMethod2 != null);
        $this->assertTrue($paymentMethod2->created_by == $user->id);
        $this->assertTrue($paymentMethod2->hasRemoteResource() == true);
        $this->assertTrue($paymentMethod2->primary_method == true);
        $this->assertTrue(PaymentMethod::find($paymentMethod->id)->primary_method == false);

        $amount      = 10.00;
        $description = 'Test charge';

        //  Test successfully charging a payment method
        $charge      = $paymentMethod->charge($amount, $description);
        $this->assertTrue($charge != null);
        $this->assertTrue($charge->payment_method_id == $paymentMethod->id);
        $this->assertTrue($charge->amount == $amount);
        $this->assertTrue($charge->description == $description);
        $this->assertTrue($charge->external_id != null);

        //  Test a failed charge attempt
        $charge      = $paymentMethod2->charge($amount, $description);
        $chargeError = $paymentMethod2->lastChargeError();
        $this->assertTrue($charge == null);
        $this->assertTrue($chargeError != null);
        $this->assertTrue($chargeError->payment_method_id == $paymentMethod2->id);
        $this->assertTrue($chargeError->amount == $amount);
        $this->assertTrue($chargeError->description == $description);
        $this->assertTrue($chargeError->error == ChargeError::PAYMENT_DECLINED);
        $this->assertTrue($chargeError->exception != null);
        
        //  Test removing a payment method
        $paymentMethod->delete();
        $this->assertTrue($paymentMethod->hasRemoteResource() == false);
        $this->assertTrue(PaymentMethod::find($paymentMethod->id) == null);
    }
}
