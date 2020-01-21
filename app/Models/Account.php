<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Exception;

class Account extends Model
{
    use SoftDeletes;

    protected $table = 'accounts'; 

    protected $fillable = [
        'name',
        'country',
        'balance',
        'auto_reload_minimum',
        'auto_reload_enabled_at',
        'rates'
    ];

    protected $hidden = [
        'external_id',
        'disabled_at',
        'deleted_at'
    ];

    protected $casts = [
        'rates' => 'array'
    ];

    /**
     * Relationships
     * 
     * 
     */
    public function payment_methods()
    {
        return $this->hasMany('\App\Models\PaymentMethod');
    }

    /**
     * Determine if an object can be purchased
     * 
     */
    public function canPurchase($object, $count = 1)
    {
        $requiredBalance = $this->price($object) * $count;
        
        //  Check account balance
        if( $this->balance >= $requiredBalance )
            return true;

        //  If auto reload is turned on and there is a valid payent method
        return $this->auto_reload_enabled_at && $this->hasValidPaymentMethod();
    }

    /**
     * Determine the price of an object by rates
     * 
     */
    public function price($object)
    {
        $rates = json_decode($this->rates, true);

        if( ! isset($rates[$object]) )
            throw new Exception('Unknown purchasable object ' . $object);

        return floatval( $rates[$object] );
    }

    /**
     * Determine if the account has a valid payment method
     * 
     */
    public function hasValidPaymentMethod()
    {
        foreach($this->payment_methods as $paymentMethod){
            if( $paymentMethod->isValid() )
                return true;
        }

        return false;
    }
}
