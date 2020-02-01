<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Purchase;
use Exception;

class Account extends Model
{
    use SoftDeletes;

    protected $table = 'accounts'; 

    protected $fillable = [
        'plan',
        'name',
        'timezone',
        'balance',
        'auto_reload_minimum',
        'auto_reload_enabled_at',
        'bill_at',
        'last_billed_at'        
    ];

    protected $hidden = [
        'external_id',
        'disabled_at',
        'deleted_at',
        'last_billed_at',
        'bill_at',
        'stripe_id',
    ];

    private $rates = [
        'BASIC' => [
            'Plan'                 => 9.99,
            'PhoneNumber.Local'    => 3.00,
            'PhoneNumber.TollFree' => 5.00,
            'Minute.Local'         => 0.04,
            'Minute.TollFree'      => 0.07,
            'SMS'                  => 0.025
        ],
        'AGENCY' =>  [
            'Plan'                 => 29.00,
            'PhoneNumber.Local'    => 2.00,
            'PhoneNumber.TollFree' => 4.00, 
            'Minute.Local'         => 0.04,
            'Minute.TollFree'      => 0.07,
            'SMS'                  => 0.025
        ],
        'ENTERPRISE' =>  [
            'Plan'                 => 79.00,
            'PhoneNumber.Local'    => 2.00,
            'PhoneNumber.TollFree' => 4.00,
            'Minute.Local'         => 0.035,
            'Minute.TollFree'      => 0.065,
            'SMS'                  => 0.02
        ]
    ];

    protected $appends = [
        'link',
        'kind'
    ];

    /**
     * Relationships
     * 
     */
    public function payment_methods()
    {
        return $this->hasMany('\App\Models\PaymentMethod');
    }

    /**
     * Appends
     * 
     */
    public function getLinkAttribute()
    {
        return route('read-account');
    }

    public function getKindAttribute()
    {
        return 'Account';
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
        $rates = $this->rates[$this->plan];

        return floatval($rates[$object]);
    }

    /**
     * Purchase an object
     * 
     */
    public function purchase($companyId, $userId, $purchaseObject, $label, $identifier, $externalIdentifier = null)
    {
        $price = $this->price($purchaseObject);

        //  Reduce balance
        $this->balance -= $price;
        $this->save();

        //  Create purchase record
        return Purchase::create([
            'account_id'    => $this->id,
            'company_id'    => $companyId,
            'created_by'    => $userId,
            'object'        => $purchaseObject,
            'label'         => $label,
            'identifier'    => $identifier,
            'external_id'   => $externalIdentifier,
            'price'         => $price,
            'created_at'    => now(),
            'updated_at'    => now()
        ]);
    }

    public function getRoundedBalanceAttribute()
    {
        return money_format('%i', $this->balance);
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
