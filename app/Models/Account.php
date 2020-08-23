<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\User;
use App\Models\Alert;
use App\Models\Company\PhoneNumber;
use App\Models\Company\Call;
use App\Models\Company\CallRecording;
use Mail;
use Exception;
use DB;
use DateTime;

class Account extends Model
{
    use SoftDeletes;

    const SUSPENSION_CODE_NO_PAYMENT_METHOD                 = 1;
    const SUSPENSION_CODE_TOO_MANY_FAILED_BILLING_ATTEMPTS  = 2;

    const MAX_DEMO_NUMBER_COUNT = 2;

    protected $fillable = [
        'name',
        'default_tts_voice',
        'default_tts_language',
        'suspended_at',
        'suspension_code',
        'suspension_warning_at',
        'suspension_message',
    ];

    protected $hidden = [
        'suspension_code',
        'suspension_warning_at',
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
                    ->orderBy('primary_method', 'desc');
    }

    public function billing()
    {
        return $this->hasOne('App\Models\Billing');
    }

    public function services()
    {
        return $this->hasMany('App\Models\Service');
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
}
