<?php 
namespace App\Services;

use App;
use Exception;
use App\Traits\CanSwapNumbers;

class SessionService
{
    use CanSwapNumbers;

    public function getSource($sourceCsvFieldList, $httpReferrer, $landingUrl)
    {
        //  Came in as param
        $source = $this->getParam($landingUrl, $sourceCsvFieldList);
        if( $source ) return $source;

        // Direct hit (No referrer or referrer same as landing url)
        if( $this->getIsDirect($httpReferrer, $landingUrl) ){
            return 'Direct';
        }

        // See if this is in our known list of referrers
        $knownReferrer = $this->getKnownReferrer($httpReferrer);
        if( $knownReferrer ){
            return $knownReferrer;
        }

        return substr($httpReferrer, 0, 512);
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

    public function getIsDirect($httpReferrer, $landingUrl)
    {
        return $this->isDirect($httpReferrer, $landingUrl);
    }

    public function getIsReferral($httpReferrer, $landingUrl)
    {
        return $this->isReferral($httpReferrer, $landingUrl);
    }

    public function getIsSearch($httpReferrer)
    {
        return $this->isSearch($httpReferrer);
    }
}