<?php 
namespace App\Services;

use App;
use Exception;
use App\Traits\CanSwapNumbers;

class SessionService
{
    use CanSwapNumbers;

    public function getSource($sourceCsvFieldList, $httpReferrer, $landingUrl, $useReferrerWhenEmpty = true)
    {
        $source = $this->getParam($landingUrl, $sourceCsvFieldList);
        if( $source ) return $source;

        if( $useReferrerWhenEmpty ){
            $referrer = substr($httpReferrer, 0, 512);
            
            if( $referrer ) return $referrer;
        }
        
        return null;
    }

    public function getMedium($mediumCsvFieldList, $landingUrl)
    {
        return $this->getParam($landingUrl, $mediumCsvFieldList);
    }

    public function getContent($contentCsvFieldList, $landingUrl)
    {
        return $this->getParam($landingUrl, $contentCsvFieldList);
    }

    public function getCampaign($campaignCsvFieldList, $landingUrl)
    {
        return $this->getParam($landingUrl, $campaignCsvFieldList);
    }

    public function getKeyword($keywordCsvFieldList, $landingUrl)
    {
        return $this->getParam($landingUrl, $keywordCsvFieldList);
    }

    public function getIsOrganic($mediumCsvFieldList, $httpReferrer, $landingUrl)
    {
        return $this->isOrganic($httpReferrer, $landingUrl, $mediumCsvFieldList);
    }

    public function getIsPaid($mediumCsvFieldList, $landingUrl)
    {
        return $this->isPaid($landingUrl, $mediumCsvFieldList);
    }

    public function getIsDirect($httpReferrer)
    {
        return $this->isDirect($httpReferrer);
    }

    public function getIsReferral($httpReferrer)
    {
        return $this->isReferral($httpReferrer);
    }

    public function getIsSearch($httpReferrer)
    {
        return $this->isSearch($httpReferrer);
    }
}