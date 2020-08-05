<?php
namespace App\Helpers;

use App;
use \Stripe\Stripe;
use \Stripe\Customer;
use \Stripe\Card;
use \Stripe\Charge as StripeCharge;
use \Stripe\PaymentMethod as StripePaymentMethod;
use App\Models\Account;
use App\Models\PaymentMethod;
use App\Models\Payment;

class PaymentManager
{
    public function charge(PaymentMethod $paymentMethod, float $amount, string $description = 'MarketFlows, LLC', $attempts = 1)
    {
        if( $attempts >= 3 )
            return null;

        try{
            Stripe::setApiKey(env('STRIPE_SK'));

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