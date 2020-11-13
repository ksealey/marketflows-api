<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\User;
use App\Models\Alert;
use App\Models\Company\PhoneNumber;
use App\Models\Company\Call;
use App\Models\Company\CallRecording;
use App\Mail\AccountUnsuspended;
use Mail;
use Exception;
use DB;
use DateTime;

class Account extends Model
{
    use SoftDeletes;

    const SUSPENSION_CODE_OUSTANDING_BALANCE = 'OUSTANDING_BALANCE';

    protected $fillable = [
        'name',
        'tts_voice',
        'tts_language',
        'source_param',
        'medium_param',
        'content_param',
        'campaign_param',
        'keyword_param',
        'source_referrer_when_empty',
        'suspended_at',
        'suspension_message',
    ];

    protected $hidden = [
        'deleted_by',
        'suspension_code',
        'deleted_at'
    ];

    protected $appends = [
        'link',
        'kind'
    ];

    protected $currentStorage;
    protected $currentUsage;

    /**
     * Relationships
     * 
     */
    public function payment_methods()
    {
        return $this->hasMany('\App\Models\PaymentMethod')
                    ->orderBy('primary_method', 'desc')
                    ->orderBy('created_at', 'desc');
    }

    public function billing()
    {
        return $this->hasOne('App\Models\Billing');
    }

    public function companies()
    {
        return $this->hasMany('App\Models\Company');
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

    public function getAdminUsersAttribute()
    {
        return User::where('account_id', $this->id)
                   ->where('role', User::ROLE_ADMIN)
                   ->get();
    }

    public function getPrimaryPaymentMethodAttribute()
    {
        return PaymentMethod::where('account_id', $this->id)
                            ->where('primary_method', 1)
                            ->first();
    }

    public function currentBalance()
    {
        $usageBalance = $this->usageBalance();

        return $usageBalance + $this->monthly_fee;
    }

    public function usageBalance()
    {
        $storage = $this->currentStorage();
        $usage   = $this->currentUsage();

        return $storage['total']['cost'] + $usage['total']['cost'];
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

    public function unsuspend()
    {
        $billing = $this->billing;
        $billing->next_suspension_warning_at = null;
        $billing->suspension_warnings        = 0;
        $billing->save();

        $this->suspended_at       = null;
        $this->suspension_message = null;
        $this->suspension_code    = null;
        $this->save();

        $this->removePaymentAlerts();
                    
        foreach( $this->admin_users as $user ){
            Mail::to($user->email)
                ->queue(new AccountUnsuspended($user));
        }
    }

    public function removePaymentAlerts()
    {
        Alert::where('category', Alert::CATEGORY_PAYMENT)
             ->where('account_id', $this->id)
             ->delete();
    }
}
