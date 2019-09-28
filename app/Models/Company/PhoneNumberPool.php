<?php

namespace App\Models\Company;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use \App\Models\Company\Campaign;
use \App\Contracts\CanBeDialed;
use \App\Traits\IsDialed;

class PhoneNumberPool extends Model implements CanBeDialed
{
    use SoftDeletes, IsDialed;

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

    public function isInUse($excludingCampaignId = null)
    {
        if( ! $this->campaign_id )
            return false;

        if( $excludingCampaignId && $excludingCampaignId == $this->campaign_id)
            return  false;
        
        return true;
    }
}
