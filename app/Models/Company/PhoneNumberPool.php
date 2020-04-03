<?php

namespace App\Models\Company;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use \App\Models\Company\PhoneNumber;
use \App\Models\Company\PhoneNumberConfig;
use \App\Traits\CanSwapNumbers;
use Exception;
use DateTime;
use DateTimeZone;
use stdClass;

class PhoneNumberPool extends Model
{
    use SoftDeletes, CanSwapNumbers;

    static public $currentAvailablePhoneList = [];

    protected $fillable = [
        'company_id',
        'user_id',
        'phone_number_config_id',
        'override_campaigns',
        'name',
        'referrer_aliases',
        'swap_rules',
        'toll_free',
        'starts_with',
        'disabled_at'
    ];

    protected $hidden = [
        'deleted_at'
    ];

    protected $appends = [
        'link',
        'kind'
    ];

    protected $casts = [
        'swap_rules'       => 'array',
        'referrer_aliases' => 'array'
    ];

    protected $dateFormat = 'Y-m-d H:i:s.u';  

    /**
     * Relationships
     * 
     * 
     */
    public function company()
    {
        return $this->belongsTo('\App\Models\Company');
    }

    public function phone_numbers()
    {
        return $this->hasMany('\App\Models\Company\PhoneNumber');
    }

    public function phone_number_config()
    {
        return $this->belongsTo('\App\Models\Company\PhoneNumberConfig');
    }

    /**
     * Attached attributes
     * 
     */
    public function getLinkAttribute()
    {
        return route('read-phone-number-pool', [
            'companyId'         => $this->company_id,
            'phoneNumberPoolId' => $this->id
        ]);
    }

    public function getKindAttribute()
    {
        return 'PhoneNumberPool';
    }

    /**
     * Determine if the phone number pool is in use
     * 
     */
    public function isInUse()
    {
        if( count($this->phone_numbers) )
            return true;
        
        return false;
    }

    /**
     * Get and assign the next phone number in line
     * 
     */
    public function assignNextNumber($preferredPhoneId = null)
    {
        $phoneNumber = $this->nextNumber($preferredPhoneId);
        if( ! $phoneNumber )
            return null;

        $now = new DateTime();
        $phoneNumber->last_assigned_at = now()->format('Y-m-d H:i:s.u');
        $phoneNumber->assignments++;
        $phoneNumber->save();

        return $phoneNumber;
    }

    /**
     * Get the next number in line
     * 
     */
    public function nextNumber($preferredPhoneId = null)
    {
        $phoneNumber = null;
        
        if( $preferredPhoneId )
            $phoneNumber = PhoneNumber::where('phone_number_pool_id', $this->id)
                                    ->where('id', $preferredPhoneId)
                                    ->first();

        if( ! $phoneNumber )
            $phoneNumber = PhoneNumber::where('phone_number_pool_id', $this->id)
                                    ->orderBy('last_assigned_at', 'ASC')
                                    ->orderBy('id', 'ASC')
                                    ->first();

        return $phoneNumber;
    }
}
