<?php

namespace App\Models\Company;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use \App\Models\Company\Campaign;
use \App\Models\Company\PhoneNumber;
use \App\Models\Company\PhoneNumberConfig;
use \App\Models\Company\PhoneNumberPoolProvisionRule;
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
        'created_by',
        'phone_number_config_id',
        'name',
        'category',
        'sub_category',
        'source',
        'source_param',
        'referrer_aliases',
        'swap_rules'
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
     * Get the next phone number in line
     * 
     */
    public function assignPhoneNumber($preferredPhoneId = null)
    {
        $phoneNumber = null;
        if( $preferredPhoneId ){
            $phoneNumber = PhoneNumber::where('phone_number_pool_id', $this->id)
                                    ->where('id', $preferredPhoneId)
                                    ->first();
        }

        if( ! $phoneNumber ){
            $phoneNumber = PhoneNumber::where('phone_number_pool_id', $this->id)
                                    ->orderBy('last_assigned_at', 'ASC')
                                    ->orderBy('id', 'ASC')
                                    ->first();
        }

        if( ! $phoneNumber )
            return null;

        $now = new DateTime(null, new DateTimeZone('UTC'));
        $phoneNumber->last_assigned_at = $now->format('Y-m-d H:i:s.u');
        $phoneNumber->assignments++;
        $phoneNumber->save();

        return $phoneNumber;
    }
}
