<?php
namespace App\Helpers;

use App;
use \Stripe\Stripe;
use \Stripe\Customer as StripeCustomer;
use \Stripe\Charge as StripeCharge;
use \Stripe\SetupIntent as StripeSetupIntent;
use \Stripe\PaymentIntent as StripePaymentIntent;
use \Stripe\PaymentMethod as StripePaymentMethod;
use App\Models\Account;
use App\Models\PaymentMethod;
use App\Models\Payment;
use App\Models\BillingStatement;
use DB;

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
    public function createCustomer(Account $account)
    {
        return StripeCustomer::create([
            'name'        => $account->name,
            'description' => $account->name . ' (' . $account->id . ')',
            'metadata'    => [
                'AccountName' => $account->name, 
                'AccountID'   => $account->id
            ]
        ]);
    }

    public function deleteCustomer($customerId)
    {
        $stripe = new \Stripe\StripeClient(config('services.stripe.secret'));
        return $stripe->customers->delete( $customerId, [] );
    }

    public function createSetupIntent($customerId)
    {
        return StripeSetupIntent::create([
            'customer' => $customerId
        ]);
    }

    public function createPaymentIntent($customerId, $paymentMethodId, $total, $description = 'MarketFlows, LLC')
    {
        return StripePaymentIntent::create([
            'currency'          => 'usd',
            'customer'          => $customerId,
            'payment_method'    => $paymentMethodId,
            'off_session'       => true,
            'confirm'           => true,
            'amount'            => $total * 100,
            'description'       => $description
        ]);
    }

    public function getIntent($intentId)
    {
        return StripePaymentIntent::retrieve($intentId);
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
    public function charge(PaymentMethod $paymentMethod, BillingStatement $statement, string $description = 'MarketFlows, LLC', $attempts = 1)
    {
        $result = [
            'payment' => null,
            'intent'  => null,
            'error'   => null
        ];

        $total = $statement->total;
        
        if( $attempts >= 3 || ! $total )
            return $result;
        
        $statement->next_payment_attempt_at = now()->addDays(3);
        $statement->payment_attempts++;
        $statement->save();
        
        try {
            $paymentIntent = $this->createPaymentIntent(
                $paymentMethod->account->billing->external_id,
                $paymentMethod->external_id,
                $total,
                $description
            );
            
            $paymentMethod->last_error           = null;
            $paymentMethod->last_error_code      = null;
            $paymentMethod->last_error_intent_id = null;
            $paymentMethod->last_error_intent_secret = null;
            $paymentMethod->last_used_at         = now();
            $paymentMethod->save();

            $payment = Payment::create([
                'payment_method_id'     => $paymentMethod->id,
                'billing_statement_id'  => $statement->id,
                'external_id'           => $paymentIntent->id,
                'total'                 => $total
            ]);

            $statement->intent_id               = $paymentIntent->id;
            $statement->next_payment_attempt_at = null;
            $statement->paid_at                 = now();
            $statement->save();

            $result['payment'] = $payment;
            $result['intent']  = $paymentIntent;
        }catch(\Stripe\Exception\RateLimitException $e){
            //  We hit a rate limit
            //  Wait a second and try again
            usleep(1);

            return $this->charge($paymentMethod, $statement, $description, $attempts + 1);
        }catch (\Stripe\Exception\CardException $e) {
            $error                               = $e->getError();
            $paymentIntent                       = $error->payment_intent;
            $paymentMethod->last_error           = $error->message;
            $paymentMethod->last_error_code      = $error->code;
            $paymentMethod->last_error_intent_id = $paymentIntent->id;
            $paymentMethod->last_error_intent_secret = $paymentIntent->client_secret;
            $paymentMethod->save();

            $statement->intent_id = $paymentIntent->id;
            $statement->save();

            $result['intent'] = $paymentIntent;
            $result['error']  = $error;
        }

        return (object)$result;
    }
}