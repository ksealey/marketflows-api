<?php

namespace App\Models\Company;

use Illuminate\Database\Eloquent\Model;
use App\Traits\CanSwapNumbers;

class KeywordTrackingPoolSession extends Model
{
    use CanSwapNumbers;

    protected $dateFormat = 'Y-m-d H:i:s.u';

    public $fillable = [
        'guuid',
        'uuid',
        'keyword_tracking_pool_id',
        'phone_number_id',
        'contact_id',
        'device_width',
        'device_height',
        'device_type',
        'device_browser', 
        'device_platform',
        'http_referrer',
        'landing_url',
        'last_url',
        'token',
        'active',
        'last_activity_at',
        'end_after',
        'ended_at',
        'created_at',
        'updated_at',
    ];

    public function phone_number()
    {
        return $this->belongsTo(\App\Models\Company\PhoneNumber::class);
    }

    public function keyword_tracking_pool()
    {
        return $this->belongsTo(\App\Models\Company\KeywordTrackingPool::class);
    }

    public function getSource($sourceCsvFieldList, $useReferrerWhenEmpty = true)
    {
        $source = $this->getParam($this->landing_url, $sourceCsvFieldList);
        if( $source ) return $source;

        if( $useReferrerWhenEmpty ){
            $referrer = substr($this->http_referrer, 0, 512);
            
            if( $referrer ) return $referrer;
        }
        
        return null;
    }

    public function getMedium($mediumCsvFieldList)
    {
        return $this->getParam($this->landing_url, $mediumCsvFieldList);
    }

    public function getContent($contentCsvFieldList)
    {
        return $this->getParam($this->landing_url, $contentCsvFieldList);
    }

    public function getCampaign($campaignCsvFieldList)
    {
        return $this->getParam($this->landing_url, $campaignCsvFieldList);
    }

    public function getKeyword($keywordCsvFieldList)
    {
        return $this->getParam($this->landing_url, $keywordCsvFieldList);
    }

    public function getIsOrganic($mediumCsvFieldList)
    {
        return $this->isOrganic($this->http_referrer, $this->landing_url, $mediumCsvFieldList);
    }

    public function getIsPaid($mediumCsvFieldList)
    {
        return $this->isPaid($this->landing_url, $mediumCsvFieldList);
    }

    public function getIsDirect()
    {
        return $this->isDirect($this->http_referrer);
    }

    public function getIsReferral()
    {
        return $this->isReferral($this->http_referrer);
    }

    public function getIsSearch()
    {
        return $this->isSearch($this->http_referrer);
    }
}
