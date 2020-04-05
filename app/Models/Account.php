<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\User;
use App\Models\Transaction;
use App\Models\Alert;
use App\Mail\Errors\AutoReloadFailed;
use App\Mail\Errors\PrimaryPaymentMethodFailed;
use App\Mail\Errors\AccountBalanceLow;
use Mail;
use Exception;
use DB;

class Account extends Model
{
    use SoftDeletes;

    protected $table = 'accounts'; 

    protected $fillable = [
        'plan',
        'name',
        'balance',
        'auto_reload_enabled_at',
        'auto_reload_minimum',
        'auto_reload_amount',
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
            'Minute.Local'         => 0.045,
            'Minute.TollFree'      => 0.07,
            'Minute.Recording'     => 0.01,
            'CallerId.Lookup'      => 0.02,
            'SMS'                  => 0.025
        ],
        'AGENCY' =>  [
            'Plan'                 => 29.00,
            'PhoneNumber.Local'    => 2.00,
            'PhoneNumber.TollFree' => 4.00, 
            'Minute.Local'         => 0.04,
            'Minute.TollFree'      => 0.07,
            'Minute.Recording'     => 0.01,
            'CallerId.Lookup'      => 0.02,
            'SMS'                  => 0.025
        ],
        'ENTERPRISE' =>  [
            'Plan'                 => 79.00,
            'PhoneNumber.Local'    => 2.00,
            'PhoneNumber.TollFree' => 4.00,
            'Minute.Local'         => 0.035,
            'Minute.TollFree'      => 0.065,
            'Minute.Recording'     => 0.01,
            'CallerId.Lookup'      => 0.02,
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

    public function getRoundedBalanceAttribute()
    {
        return money_format('%i', $this->balance);
    }

    public function getPrimaryPaymentMethodAttribute()
    {
        return PaymentMethod::where('account_id', $this->id)
                            ->where('primary_method', 1)
                            ->first();
    }

    public function getAdminUsersAttribute()
    {
        return User::where('account_id', $this->id)
                   ->whereIn('id', function($query){
                        $query->select('user_id')
                              ->from('user_roles')
                              ->where('');
                   });
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
     * Determine if an object can be purchased
     * 
     */
    public function balanceCovers($object, $count = 1)
    {
        $requiredBalance = $this->price($object) * $count;
        
        //  Check account balance
        if( $this->balance >= $requiredBalance )
            return true;

        //  If auto reload is turned on and there is a valid payent method
        return $this->auto_reload_enabled_at && $this->hasValidPaymentMethod();
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

    /**
     * Create transaction, reducing the account balance
     * 
     */
    public function transaction($type, $item, $table, $recordId, $label, $companyId = null, $userId = null)
    {
        $price = $this->price($item);

        //  Log transaction
        $transaction = Transaction::create([
            'account_id'    => $this->id,
            'amount'        => $price,
            'type'          => $type,
            'item'          => $item,
            'table'         => $table,
            'record_id'     => $recordId,
            'label'         => $label,
            'company_id'    => $companyId,
            'user_id'       => $userId,
            'created_at'    => now()
        ]);

        $this->reduceBalance($price);

        return $transaction;
    }

    public function reduceBalance($amount)
    {
        $this->balance -= $amount;
        $this->save();

        if( $this->shouldAutoReload() )
            $this->autoReload();

        if( $this->shouldWarnBalanceLow() )
            $this->warnBalanceLow();
    }

    /**
     * Determine if we should auto reload account
     * 
     */
    public function shouldAutoReload()
    {
        return $this->auto_reload_enabled_at && $this->balance < $this->auto_reload_minimum;
    }

    /**
     * Determine if we should warn users about a low balance
     * 
     */
    public function shouldWarnBalanceLow()
    {
        return ! $this->auto_reload_enabled_at && $this->balance <= 5.00;
    }

    /**
     * Auto-reload account when balance gets below threshold
     * 
     */
    public function autoReload()
    {
        $primaryMethod  = $this->primary_payment_method;
        $amount         = $this->auto_reload_amount;
        $desc           = env('APP_NAME') . ' - Auto Reload';

        //  Attempt to charge primary method
        $charge = $primaryMethod->charge($amount, $desc);
        if( $charge ){
            $this->balance += floatval($charge->amount);

            $this->save();

            return;
        }
        
        //  Let the admins know their default payment method failed
        $adminUsers = $this->admin_users;
        foreach( $adminUsers as $user ){
            $message = 'Your default payment method, card ending in ' 
                        . $primaryMethod->last_4 
                        . ' was declined while attempting to reload your account. Please update to avoid disruption in service.';
        
            $alert = $this->alert(
                $user, 
                Alert::CATEGORY_ERROR, 
                Alert::TYPE_PRIMARY_METHOD_FAILED,
                $message
            );

            if( ! $alert )
                continue;

            $user->email(new PrimaryPaymentMethodFailed());
            $user->sms($message);
        }

        //  Try additional payment methods, starting with the newest first
        $paymentMethods = PaymentMethod::where('account_id', $this->id)
                                        ->where('primary_method', 0)
                                        ->orderBy('created_at', 'desc')
                                        ->get();

        foreach( $paymentMethods as $pm ){
            $charge = $pm->charge($amount, $desc);

            if( $charge ){
                $this->balance += floatval($charge->amount);
    
                $this->save();
    
                return;
            }
        }

        //  Let the admin user know that NO payment methods succedded and their account is subject to disruption
        foreach( $adminUsers as $user ){
            $message = 'All payment methods on your account failed and your account balance was not reloaded.' 
                       . 'Service will be disrupted if your account balance falls below $0.';

            $alert = $this->alert(
                $user, 
                Alert::CATEGORY_ERROR, 
                Alert::TYPE_AUTO_RELOAD_FAILED,
                $message
            );

            if( ! $alert )
                continue;

            $user->email(new AutoReloadFailed());
            $user->sms($message);
        }
    }

    /**
     * Warn all admin user that their account balance is low
     * 
     */
    public function warnBalanceLow()
    {
        foreach( $this->admin_users as $user ){
            $message = 'Your account balance is less than $5. Please reload your account or turn on auto-reload to avoid disruption in your service.';
            
            $alert = $this->alert(
                $user, 
                Alert::CATEGORY_ERROR, 
                Alert::TYPE_BALANCE_LOW,
                $message
            );

            if( ! $alert )
                continue;

            $user->email(new AccountBalanceLow());
            $user->sms($message);
        }
    }

    /**
     * Alert a user
     * 
     */
    public function alert(User $user, $category, $type, $message, $gapHours = 24)
    {
        $existingAlert = Alert::where('user_id', $user->id)
                            ->where('category', $category) 
                            ->where('type', $type)
                            ->where('created_at', '>=', now()->subHours($gapHours))
                            ->first();

        if( $existingAlert )
            return null;

        return Alert::create([
            'user_id'  => $user->id,
            'category' => $category,
            'type'     => $type,
            'message'  => $message
        ]);
    }
}
