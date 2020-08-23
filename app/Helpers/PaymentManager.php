<?php
namespace App\Helpers;

use App;
use \Stripe\Stripe;
use \Stripe\Customer as StripeCustomer;
use \Stripe\Charge as StripeCharge;
use \Stripe\PaymentMethod as StripePaymentMethod;
use App\Models\Account;
use App\Models\PaymentMethod;
use App\Models\Payment;

class PaymentManager
{
    public function __construct()
    {
        Stripe::setApiKey(config('services.stripe.secret'));
    }

    /**
     * Create and return a customer
     * 
     * @param string $name 
     * @param string $token
     * 
     * @return \Stripe\Customer
     */
    public function createCustomer(string $name, string $token)
    {
        return StripeCustomer::create([
            'description'    => $name,
            'payment_method' => $token
        ]);
    }

    /**
     * Get a list of payment methods for a given customer
     * 
     * @param int $customerId
     * 
     * @return \Stripe\PaymentMethod
     */
    public function getPaymentMethods($customerId, $type = 'card')
    {
        return StripePaymentMethod::all([
            'customer' => $customerId,
            'type'     => $type
        ]);
    }

    /**
     * Charge a payment method
     * 
     */
    public function charge(PaymentMethod $paymentMethod, float $amount, string $description = 'MarketFlows, LLC', $attempts = 1)
    {
        if( $attempts >= 3 )
            return null;

        try{
            
            $stripeCharge = StripeCharge::create([
                'customer'      => $paymentMethod->account->billing->external_id,
                'source'        => $paymentMethod->external_id,
                'amount'        => $amount * 100,
                'currency'      => 'usd',
                'description'   => $description
            ]);

            $paymentMethod->last_used_at = now();
            $paymentMethod->error        = null;
            $paymentMethod->save();

            return Payment::create([
                'payment_method_id' => $paymentMethod->id,
                'external_id'       => $stripeCharge->id,
                'total'             => $amount
            ]);
        }catch(\Stripe\Exception\RateLimitException $e){
            //  We hit a rate limit
            //  Wait a second and try again
            usleep(1);

            return $this->charge($paymentMethod, $amount, $description, $attempts + 1);
        }catch(Exception $e){
            $paymentMethod->last_used_at = now();
            $paymentMethod->error        = substr($e->getMessage(), 0, 255);
            $paymentMethod->save();

            return false;
        }
    }
}