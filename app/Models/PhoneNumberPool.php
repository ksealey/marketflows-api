<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use \App\Models\CampaignPhoneNumberPool;

class PhoneNumberPool extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'created_by',
        'name', 
        'source', 
        'forward_to_country_code',
        'forward_to_number',
        'audio_clip_id'
    ];

    protected $hidden = [
        'company_id',
        'created_by',
        'deleted_at',
        'audio_clip_id'
    ];

    public function isInUse($campaignId = null)
    {
        $query = CampaignPhoneNumberPool::where('phone_number_pool_id', $this->id);
        if( $campaignId )
            $query->where('campaign_id', '!=', $campaignId);
        $linkCount = $query->count();

        return $linkCount ? true : false;
    }

    public function isInUseExcludingCampaign($campaignId = null)
    {
        return $this->isInUse($campaignId);
    }
}
