<?php

namespace App\Models\Company;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use \App\Models\Company\Campaign;
use \App\Models\Company\PhoneNumberConfig;
use \App\Contracts\CanAcceptIncomingCalls;
use \App\Traits\AcceptsIncomingCalls;

class PhoneNumberPool extends Model implements CanAcceptIncomingCalls
{
    use SoftDeletes, AcceptsIncomingCalls;

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
}
