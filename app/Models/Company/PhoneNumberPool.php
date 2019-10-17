<?php

namespace App\Models\Company;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use \App\Models\Company\Campaign;
use \App\Models\Company\PhoneNumber;
use \App\Models\Company\PhoneNumberConfig;
use \App\Models\Company\PhoneNumberPoolProvisionRule;
use \App\Contracts\CanAcceptIncomingCalls;
use \App\Traits\AcceptsIncomingCalls;
use Exception;
use DateTime;
use stdClass;

class PhoneNumberPool extends Model implements CanAcceptIncomingCalls
{
    use SoftDeletes, AcceptsIncomingCalls;

    static public $currentAvailablePhoneList = [];

    protected $fillable = [
        'company_id',
        'created_by',
        'campaign_id',
        'phone_number_config_id',
        'name'
    ];

    protected $hidden = [
        'company_id',
        'created_by',
        'deleted_at'
    ];

    public function company()
    {
        return $this->belongsTo('\App\Models\Company');
    }

    public function isInUse($excludingCampaignId = null)
    {
        if( ! $this->campaign_id )
            return false;

        if( $excludingCampaignId && $excludingCampaignId == $this->campaign_id)
            return  false;
        
        return true;
    }

    public function getPhoneNumberConfig() : PhoneNumberConfig
    {
        return PhoneNumberConfig::find($this->phone_number_config_id);
    }

    /**
     * Assign a phone number
     * 
     */
    public function assignPhoneNumber($preferredPhoneUUID = null, $overrideNumber = null)
    {
        //  If there is a preffered phone number see if we can get it
        $phoneNumber = null;
        if( $preferredPhoneUUID ){
            $phoneNumber = PhoneNumber::where('uuid', $preferredPhoneUUID)
                                     ->where('phone_number_pool_id', $this->id)
                                     ->whereNull('assigned_at')
                                     ->first();
        }

        //  No preferred number, get the next available phone number
        //  Get the number that was assigned the longest time ago first
        if( ! $phoneNumber ){
            $phoneNumber = PhoneNumber::where('phone_number_pool_id', $this->id)
                                      ->whereNull('assigned_at')
                                      ->orderBy('last_assigned_at', 'ASC')
                                      ->first();
        }

        //  No phone nunmbers where available at the time,
        //  Try to buy a new phone number
        if( ! $phoneNumber )
            $phoneNumber = $this->autoProvisionPhone($overrideNumber);

        //  If we STILL don't have a phone number, we'll have to have user's share a phone number
        if( ! $phoneNumber ){
            $query = PhoneNumber::where('phone_number_pool_id', $this->id);

            if( $preferredPhoneUUID )
                $query = $query->where('uuid', $preferredPhoneUUID);
            else
                $query = $query->orderBy('last_assigned_at', 'ASC');
                
            $phoneNumber = $query->first();
        }

        if( $phoneNumber ){
            $now = new DateTime();
            $phoneNumber->last_assigned_at = $now->format('Y-m-d H:i:s.u');
            $phoneNumber->assigned_at      = $now->format('Y-m-d H:i:s.u');
            $phoneNumber->save();
        }

        return $phoneNumber ?: null;
    }

    /**
     * Automatically provision a phone number based on the settings and provision rules attached
     * 
     * @param string $overrideNumber     A number that will override any rules found
     * 
     * @return 
     */
    public function autoProvisionPhone($overrideNumber = null)
    {
        //  First make sure we're even allowed to do this
        if( ! $this->auto_provision_enabled_at )
            return null;
    
        //  This user has auto-provisioning on,
        //  Make sure we haven't exceeded our limit of phone numbers
        $currentPhoneCount = PhoneNumber::where('phone_number_pool_id', $this->id)
                                        ->count();

        if( $currentPhoneCount >= intval($this->auto_provision_max_allowed) )
            return null;

        //  Make sure we haven't exceeded the phone number limit on the company
        $company = $this->company;
        if( $company->phone_number_max_allowed && $currentPhoneCount >= intval($company->phone_number_max_allowed) )
            return null;

        //  Looks like we're ok to buy another phone number
        $provisionRules = PhoneNumberPoolProvisionRule::where('phone_number_pool_id', $this->id)
                                                  ->orderBy('priority', 'ASC')
                                                  ->get();
        $phoneNumber = null;
        if( $provisionRules ){
            foreach( $provisionRules as $provisionRule ){
                $phoneNumber = $this->provisionPhone($provisionRule,  $overrideNumber);

                if( $phoneNumber )
                    break;
            }
        }

        return $phoneNumber;
    }

    /**
     * Provision a phone number using given rule
     * 
     * @param PhoneNumberPoolProvisionRule $provisionRule   The rule to be used
     * @param string $overrideNumber                        A number that will override any rules given
     * 
     * @return PhoneNumber
     */
    public function provisionPhone(PhoneNumberPoolProvisionRule $provisionRule, $overrideNumber = null)
    {
        $iteration = 0;
        while( $overrideNumber || $numbersAvailable = PhoneNumber::listAvailable($provisionRule->country, $provisionRule->areaCode, 5) ){
            //  Only make 4 attempts(20 numbers total)
            if( $iteration >= 4 )
                return null;
            $iteration++;

            //  If one set number...
            if( $overrideNumber ){
                $numberAvailable = new stdClass();
                $numberAvailable->phoneNumber = $overrideNumber;
                $numbersAvailable = [$numberAvailable];
            }

            //  Iterate through each of the 5 numbers
            foreach( $numbersAvailable as $numberAvailable ){
                try{
                    $purchasedPhone = PhoneNumber::purchase($numberAvailable->phoneNumber);

                    return PhoneNumber::create([
                        'uuid'                                => Str::uuid(),
                        'company_id'                          => $this->company_id,
                        'auto_provisioned_by'                 => $this->id,
                        'phone_number_config_id'              => $this->phone_number_config_id,
                        'external_id'                         => $purchasedPhone->sid,
                        'country_code'                        => PhoneNumber::countryCode($purchasedPhone->phoneNumber),
                        'number'                              => PhoneNumber::number($purchasedPhone->phoneNumber),
                        'voice'                               => $purchasedPhone->capabilities['voice'],
                        'sms'                                 => $purchasedPhone->capabilities['sms'],
                        'mms'                                 => $purchasedPhone->capabilities['mms'],
                        'phone_number_pool_id'                => $this->id,
                        'phone_number_pool_provision_rule_id' => $provisionRule->id,
                        'name'                                => substr('(Auto-Provisioned) - ' . $this->name, 0, 255),
                    ]);
                }catch(\Twilio\Exceptions\RestException $e){
                    if( $e->getCode() == PhoneNumber::ERROR_CODE_UNAVAILABLE )
                        continue; // Only try again if it failed for being unavailable
                }
                return null;
            }
        }
        return null;
    }
}
