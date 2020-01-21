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

    protected $hidden = [
        'account_id',
        'external_id',
        'created_by',
        'deleted_at'
    ];

    protected $fillable = [
        'account_id',
        'created_by',
        'external_id',
        'last_4',
        'exp_year',
        'exp_month',
        'brand',
        'type',
        'primary_method'
    ];

    public function account()
    {
        return $this->belongsTo('\App\Models\Account');
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
        $account = $user->account;
        if( ! $account->external_id ){
            $primaryMethod = true; // Since there is no primary methon set this as true
            $customer = Customer::create([
                'description' => $account->name,
                'source'      => $stripeToken
            ]);
            $account->external_id = $customer->id;
            $account->save();
            
            $card = $customer->sources->data[0];
        }else{
            $card = Customer::createSource(
                $account->external_id,
                ['source' => $stripeToken]
            );
        }

        //  If this is the new primary method, unset existing
        if( $primaryMethod ){
            self::where('account_id', $user->account_id)
                ->update([
                    'primary_method' => false
                ]);
        }

        return self::create([
            'account_id'     => $user->account_id,
            'created_by'     => $user->id,
            'external_id'      => $card->id,
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
        if( ! $this->external_id )
            return null;

        try{
            //  Create remote charge
            $stripeCharge = StripeCharge::create([
                'customer'      => $this->account->external_id,
                'source'        => $this->external_id,
                'amount'        => $amount * 100,
                'currency'      => 'usd',
                'description'   => $description
            ]);

            //  Resolve any existing charge errors
            ChargeError::where('payment_method_id', $this->id)
                        ->where('resolved', 0)
                        ->update([ 
                            'resolved' => 1 
                        ]);
            
            //  Create local charge 
            return Charge::create([
                'payment_method_id' => $this->id,
                'external_id'       => $stripeCharge->id,
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

        ChargeError::create([
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

        if( ! $this->external_id )
            return null;

        try{
            return StripePaymentMethod::retrieve($this->external_id);
        }catch(Exception $e){
            return null;
        }
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

    /**
     * Determine if a payment method is valid
     * 
     */
    public function isValid()
    {
        $chargeErrorCount = ChargeError::where('payment_method_id', $this->id)
                                        ->where('resolved', 0)
                                        ->count();

        return $chargeErrorCount ? false : true;
    }


}
