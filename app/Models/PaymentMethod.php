<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use \App\Models\User;
use \App\Models\Charge;
use \App\Models\ChargeError;
use \Stripe\Stripe;
use \Stripe\Customer;
use \Stripe\Card;
use \Stripe\Charge as StripeCharge;
use Stripe\PaymentMethod as StripePaymentMethod;
use DB;
use Exception;

class PaymentMethod extends Model
{
    use SoftDeletes;

    private $lastChargeError;

    protected $hidden = [
        'company_id',
        'stripe_id',
        'created_by',
        'deleted_at'
    ];

    protected $fillable = [
        'company_id',
        'created_by',
        'stripe_id',
        'last_4',
        'exp_year',
        'exp_month',
        'brand',
        'type',
        'primary_method'
    ];

    public function company()
    {
        return $this->belongsTo('\App\Models\Company');
    }

    public function charges()
    {
        return $this->hasMany('\App\Models\Charge');
    }

    public function chargeErrors()
    {
        return $this->hasMany('\App\Models\ChargeError');
    } 

    /**
     * Create a payment method using a token
     * 
     */
    static public function createFromToken(string $stripeToken, User $user, $primaryMethod = false)
    {
        //
        //  Create remote resources
        //
        Stripe::setApiKey(env('STRIPE_SK'));

        //  Create customer with source if not exists
        $company = $user->company;
        if( ! $company->stripe_id ){
            $primaryMethod = true; // Since there is no primary methon set this as true
            $customer = Customer::create([
                'description' => $company->name,
                'source'      => $stripeToken
            ]);
            $company->stripe_id = $customer->id;
            $company->save();
            
            $card = $customer->sources->data[0];
        }else{
            $card = Customer::createSource(
                $company->stripe_id,
                ['source' => $stripeToken]
            );
        }

        //  If this is the new primary method, unset existing
        if( $primaryMethod ){
            self::where('company_id', $user->company_id)
                ->update([
                    'primary_method' => false
                ]);
        }

        return self::create([
            'company_id'     => $user->company_id,
            'created_by'     => $user->id,
            'stripe_id'      => $card->id,
            'last_4'         => $card->last4,
            'exp_month'      => $card->exp_month,
            'exp_year'       => $card->exp_month,
            'type'           => $card->funding,
            'brand'          => $card->brand,
            'primary_method' => $primaryMethod
        ]);
    }


    /**
     * Charge payment method
     * 
     */
    public function charge(float $amount, string $description)
    {
        if( ! $this->stripe_id )
            return null;

        try{
            //  Create remote charge
            $stripeCharge = StripeCharge::create([
                'customer'      => $this->company->stripe_id,
                'source'        => $this->stripe_id,
                'amount'        => $amount * 100,
                'currency'      => 'usd',
                'description'   => $description
            ]);

            //  Remove any existing charge errors
            ChargeError::where('payment_method_id', $this->id)
                        ->delete();
            
            //  Create charge 
            return Charge::create([
                'payment_method_id' => $this->id,
                'stripe_id'         => $stripeCharge->id,
                'amount'            => $amount,
                'description'       => $description
            ]);
        }catch(\Stripe\Error\Card $e){
            $error     = substr($e->getMessage(), 0, 255);
            $exception = $e->getMessage() . "\n" . $e->getTraceAsString();
        }catch(Exception $e){
            $error     = ChargeError::PAYMENT_DECLINED;
            $exception = $e->getMessage() . "\n" . $e->getTraceAsString();
        }

        $this->lastChargeError = ChargeError::create([
            'payment_method_id' => $this->id,
            'amount'            => $amount,
            'description'       => $description,
            'error'             => $error,
            'exception'         => $exception
        ]);

        return null;
    }

    /**
     * Check if payment method has a remote resource
     * 
     */
    public function hasRemoteResource()
    {
        return $this->getRemoteResource() ? true : false;
    }

    /**
     * Get payment's remote resource
     * 
     */
    public function getRemoteResource()
    {
        Stripe::setApiKey(env('STRIPE_SK'));

        if( ! $this->stripe_id )
            return null;

        try{
            return StripePaymentMethod::retrieve($this->stripe_id);
        }catch(Exception $e){
            return null;
        }
    }

    public function lastChargeError()
    {
        if( ! $this->lastChargeError ){
            $errors = $this->chargeErrors;

            $this->lastChargeError = count($errors) ? $errors->last() : null;
        }
        
        return $this->lastChargeError;
    }

    /**
     * Delete payment along wit it's remote resource
     * 
     */
    public function delete()
    {
        Stripe::setApiKey(env('STRIPE_SK'));
        
        //  Remove from remote resource
        if( $resource = $this->getRemoteResource() )
            $resource->detach();

        //  Now call the parent's delete method
        parent::delete();
    }
}
