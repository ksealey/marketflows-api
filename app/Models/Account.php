<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\User;
use App\Models\Transaction;
use App\Models\Alert;
use App\Models\Company\PhoneNumber;
use App\Models\Company\Call;
use App\Models\Company\CallRecording;
use App\Mail\Errors\PrimaryPaymentMethodFailed;
use Mail;
use Exception;
use DB;
use DateTime;

class Account extends Model
{
    use SoftDeletes;

    const TYPE_BASIC              = 'BASIC';
    const TYPE_ANALYTICS          = 'ANALYTICS';
    const TYPE_ANALYTICS_PRO      = 'ANALYTICS_PRO';

    const TIER_NUMBERS_LOCAL      = 10;
    const TIER_NUMBERS_TOLL_FREE  = 0;
    const TIER_MINUTES_LOCAL      = 500;
    const TIER_MINUTES_TOLL_FREE  = 0;
    const TIER_STORAGE            = 1;

    const COST_TYPE_BASIC         = 45.00;
    const COST_TYPE_ANALYTICS     = 90.00;
    const COST_TYPE_ANALYTICS_PRO = 90.00;
    const COST_STORAGE_GB         = 0.25;
    const COST_NUMBER_LOCAL       = 3.00;
    const COST_NUMBER_TOLL_FREE   = 5.00;
    const COST_MINUTE_LOCAL       = 0.05;
    const COST_MINUTE_TOLL_FREE   = 0.08;

    const SUSPENSION_CODE_NO_PAYMENT_METHOD                 = 1;
    const SUSPENSION_CODE_TOO_MANY_FAILED_BILLING_ATTEMPTS  = 2;

    protected $fillable = [
        'name',
        'account_type',
        'account_type_updated_at',
        'default_tts_voice',
        'default_tts_language',
        'suspended_at',
        'suspension_code',
        'suspension_warning_at',
        'suspension_message',
    ];

    protected $hidden = [
        'account_type_updated_at',
        'suspended_at',
        'suspension_code',
        'suspension_warning_at',
        'deleted_at'
    ];

    protected $appends = [
        'link',
        'kind',
        'pretty_account_type',
        'monthly_fee',
    ];

    protected $currentStorage;
    protected $currentUsage;

    static public function types()
    {
        return [
            self::TYPE_BASIC,
            self::TYPE_ANALYTICS,
            self::TYPE_ANALYTICS_PRO
        ];
    }

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

    public function getPrettyAccountTypeAttribute()
    {
        switch($this->account_type){
            case self::TYPE_BASIC: return 'Basic';
            case self::TYPE_ANALYTICS: return 'Analytics';
            case self::TYPE_ANALYTICS_PRO: return 'Analytics Pro';
            default: return '';
        }
    }

    public function getMonthlyFeeAttribute()
    {
        switch($this->account_type){
            case self::TYPE_BASIC: return self::COST_TYPE_BASIC;
            case self::TYPE_ANALYTICS: return self::COST_TYPE_ANALYTICS;
            case self::TYPE_ANALYTICS_PRO: return self::COST_TYPE_ANALYTICS_PRO;
            default: return '';
        }
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
        $storage = $this->currentStorage();
        $usage   = $this->currentUsage();

        return $storage['total']['cost'] + $usage['total']['cost'] + $this->monthly_fee;
    }

    public function getPastDueAmountAttribute()
    {
        //  
        //  Allow an hour for the system to bill the account.
        //
        //  If over an hour has passed after it should have been billed and the bill_at date was not pushed to future date
        //  that means the total owed was not paid
        // 
        $now              = new DateTime();
        $shouldBeBilledBy = new DateTime($this->billing->bill_at);
        $shouldBeBilledBy->modify('+1 hour');

        if( $now->format('U') > $shouldBeBilledBy->format('U') )
            return $this->currentBalance();
        
        return 0.00;
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

    public function canPurchaseNumbers($count)
    {
        return true;
    }

    /**
     * Get the current usage
     * 
     */
    public function currentUsage()
    {
        if( $this->currentUsage ) return $this->currentUsage;

        $billingPeriod = $this->billing->current_billing_period;

        //
        //  Get number usage
        //
        $numbers = PhoneNumber::withTrashed()
                                ->whereIn('company_id', function($query){
                                    $query->select('id')
                                        ->from('companies')
                                        ->where('account_id', $this->id);
                                })
                                ->where(function($query) use($billingPeriod){
                                    $query->whereNull('deleted_at')
                                          ->orWhere(function($query) use($billingPeriod){
                                                $query->whereBetween('deleted_at', [
                                                    $billingPeriod['start'], 
                                                    $billingPeriod['end']
                                                ]);
                                          });
                                })
                                ->get()
                                ->toArray();

        $localNumbers = array_filter($numbers, function($number){
            return $number['type'] === PhoneNumber::TYPE_LOCAL;
        });

        $tollFreeNumbers = array_filter($numbers, function($number){
            return $number['type'] === PhoneNumber::TYPE_TOLL_FREE;
        });

        $localNumbersCost    = count($localNumbers)    > self::TIER_NUMBERS_LOCAL     ? ((count($localNumbers)- self::TIER_NUMBERS_LOCAL)  * self::COST_NUMBER_LOCAL)            : 0.00;
        $tollFreeNumbersCost = count($tollFreeNumbers) > self::TIER_NUMBERS_TOLL_FREE ? ((count($tollFreeNumbers) - self::TIER_NUMBERS_TOLL_FREE) * self::COST_NUMBER_TOLL_FREE) : 0.00;

        //
        //  Get Call usage
        //
        $minutes = DB::table('calls')
                    ->select([
                            DB::raw('SUM( CEIL(duration / 60) ) AS total_minutes'),
                            'type'
                    ])
                    ->whereIn('calls.company_id', function($query){
                            $query->select('id')
                                ->from('companies')
                                ->where('account_id', $this->id);
                    })
                    ->whereBetween('calls.created_at', [$billingPeriod['start'], $billingPeriod['end']])
                    ->groupBy('type')
                    ->get();
    
        $localMinutes     = 0;
        $tollFreeMinutes  = 0;
        foreach( $minutes as $m ){
            if( $m->type == PhoneNumber::TYPE_LOCAL ){
                $localMinutes = $m->total_minutes;
            }elseif($m->type == PhoneNumber::TYPE_TOLL_FREE){
                $tollFreeMinutes = $m->total_minutes;
            }
        }

        $localMinutesCost    = $localMinutes > self::TIER_MINUTES_LOCAL ? (($localMinutes - self::TIER_MINUTES_LOCAL) * self::COST_MINUTE_LOCAL) : 0.00;
        $tollFreeMinutesCost = $tollFreeMinutes > self::TIER_MINUTES_TOLL_FREE ? (($tollFreeMinutes - self::TIER_MINUTES_TOLL_FREE) * self::COST_MINUTE_TOLL_FREE) : 0.00;

        $this->currentUsage =  [
            'local' => [
                'numbers' => [
                    'count' => count($localNumbers),
                    'cost'  => $localNumbersCost
                ],
                'minutes' => [
                    'count' => $localMinutes,
                    'cost'  => $localMinutesCost
                ]
            ],
            'toll_free' => [
                'numbers' => [
                    'count' => count($tollFreeNumbers),
                    'cost' => $tollFreeNumbersCost
                ],
                'minutes' => [
                    'count' => $tollFreeMinutes,
                    'cost'  => $tollFreeMinutesCost
                ]
            ],
            'total' => [
                'cost' => $localNumbersCost + $localMinutesCost + $tollFreeNumbersCost + $tollFreeMinutesCost,
            ]

        ];

        return $this->currentUsage;
    }

    /**
     * Storage
     * 
     */
    public function currentStorage()
    {
        if( $this->currentStorage ) return $this->currentStorage;

        $fileStorageSize          = 0;
        $callRecordingStorageSize = DB::table('call_recordings')
                                        ->select(
                                            DB::raw(
                                                'CASE  
                                                    WHEN SUM(file_size) IS NOT NULL
                                                            THEN sum(file_size)
                                                        ELSE 0
                                                    END AS storage_size'
                                            )
                                        )
                                        ->first()
                                        ->storage_size;
        
        //  Get storage sizes in GBs
        $gbSize                     = 1024 * 1024;
        $fileStorageSizeGB          = round($fileStorageSize / $gbSize, 2);
        $callRecordingStorageSizeGB = round($callRecordingStorageSize / $gbSize, 2);
        $totalStorageSizeGB         = $callRecordingStorageSizeGB + $fileStorageSizeGB;


        //  Get the cost to account
        $fileStorageCost          = $fileStorageSizeGB          > self::TIER_STORAGE ? round(self::COST_STORAGE_GB * ($fileStorageSizeGB - self::TIER_STORAGE), 2)          : 0.00;
        $callRecordingStorageCost = $callRecordingStorageSizeGB > self::TIER_STORAGE ? round(self::COST_STORAGE_GB * ($callRecordingStorageSizeGB - self::TIER_STORAGE), 2) : 0.00;
        $totalStorageCost         = $callRecordingStorageCost + $fileStorageCost;

        $this->currentStorage = [
            'call_recordings' => [
                'cost'    => $callRecordingStorageCost,
                'size_gb' => $callRecordingStorageSizeGB,
            ],
            'files' => [
                'cost'    => $fileStorageCost,
                'size_gb' => $fileStorageSizeGB
            ],
            'total' => [
                'cost'    => $totalStorageCost,
                'size_gb' => $totalStorageSizeGB
            ]
        ];

        return $this->currentStorage;
    }    
}
