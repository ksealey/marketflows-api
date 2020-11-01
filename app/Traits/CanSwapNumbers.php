<?php
namespace App\Traits;
use \App\Models\Company;

trait CanSwapNumbers
{
    public $browserTypes = [
        'CHROME',
        'FIREFOX', 
        'INTERNET_EXPLORER', 
        'EDGE', 
        'SAFARI', 
        'OPERA',
        'OTHER'
    ];

    public $deviceTypes = [
        'DESKTOP',
        'TABLET', 
        'MOBILE',
        'OTHER'
    ];

    protected $operators = [
        'EMPTY', // No inputs
        'NOT_EMPTY', // No inputs
        'EQUALS',
        'NOT_EQUALS',
        'IN',
        'NOT_IN',
        'LIKE',
        'NOT_LIKE'
    ];

    public function swapRulesPass($swapRules, $browserType, $deviceType, $httpReferrer, $entryURL, $mediumCsvList)
    {
        //  If it fails for browser type stop here
        if( count($swapRules->browser_types) && $swapRules->browser_types[0] !== 'ALL' && ! in_array($browserType, $swapRules->browser_types) )
            return false;

         //  If it fails for device type stop here
         if( count($swapRules->device_types) && $swapRules->device_types[0] !== 'ALL' && ! in_array($deviceType, $swapRules->device_types) )
            return false;
        
        $aRuleGroupPassed = false;
        foreach( $swapRules->inclusion_rules as $ruleGroup ){
            if( $this->ruleGroupPasses($ruleGroup, $entryURL, $httpReferrer, $mediumCsvList) )
                $aRuleGroupPassed = true;
        }

        if( ! $aRuleGroupPassed )
            return false;

        //  
        //  Make sure that the exlcusion rules do not pass 
        //
        if( empty($swapRules->exclusion_rules) )
            return true;

        foreach( $swapRules->exclusion_rules as $ruleGroup ){
            if( $this->ruleGroupPasses($ruleGroup, $entryURL, $httpReferrer, $mediumCsvList) )
                return false;
        }
        
        return true;
    }

    /**
     * Determine if an entire rule group passes
     * 
     */
    public function ruleGroupPasses($ruleGroup, $entryURL, $httpReferrer, $mediumCsvList)
    {
        foreach( $ruleGroup->rules as $rule ){
            if( ! $this->rulePasses($rule, $entryURL, $httpReferrer, $mediumCsvList) )
                return false;
        }

        return true;
    }

    /**
     * Determine if a single rule passes
     * 
     */
    public function rulePasses($rule, $entryURL, $httpReferrer, $mediumCsvList)
    {
        $entryURL     = strtolower($entryURL);
        $httpReferrer = strtolower($httpReferrer);

        if( $rule->type === 'ALL' )
            return true;

        //  No referrer
        if( $rule->type === 'DIRECT' )
            return $this->isDirect($httpReferrer, $entryURL);

        //  ! is paid
        if( $rule->type === 'ORGANIC' )
            return $this->isOrganic($httpReferrer, $entryURL, $mediumCsvList);

        //  UTM medium or medium in cpc,ppc,cpa,cpm,cpv,cpp
        if( $rule->type === 'PAID' )
            return $this->isPaid($entryURL, $mediumCsvList);

        //  Referrer google, yahoo or bing
        if( $rule->type === 'SEARCH' )
            return $this->isSearch($httpReferrer); 

        if( $rule->type === 'PAID_SEARCH' ){
            return $this->isPaid($entryURL, $mediumCsvList) && $this->isSearch($httpReferrer); 
        }

        //  TODO: Add SOCIAL. Facebook, Instagram, Youtube, etc
        
        // Is a referral
        if( $rule->type === 'REFERRAL' )
            return $this->isReferral($httpReferrer, $entryURL);

        if( $rule->type === 'REFERRER' ){
            $value = $this->normalizeReferrer($httpReferrer);
        }

        if( $rule->type == 'LANDING_PATH' )
            $value = rtrim(parse_url($entryURL, PHP_URL_PATH), '/');
        
        if( $rule->type == 'LANDING_PARAM' ){
            //  Pull parameters from url
            parse_str(parse_url($entryURL, PHP_URL_QUERY), $params);
            
            //  Nothing to do here if the param does not exist
            $key = trim($rule->field);
            if( empty($params[$key]) )
                return false;

            $value = $params[$key];
        }

        $value = trim($value);

        //  Normalize inputs
        $inputs = array_map(function($input) use($rule){
            $input = trim(strtolower($input));
            if( $rule->type === 'REFERRER' )
                $input = $this->normalizeReferrer($input);;

            if( $rule->type === 'LANDING_PATH' )
                $input = rtrim($input, '/');

            return $input;
        }, $rule->inputs);

        if( $rule->operator == 'EQUALS' )
            return $inputs[0] == $value;

        if( $rule->operator == 'NOT_EQUALS' )
            return $inputs[0] != $value;
        
        if( $rule->operator == 'LIKE' )
            return stripos($value,  $inputs[0]) !== false;

        if( $rule->operator == 'NOT_LIKE' )
            return stripos($value,  $inputs[0]) !== false;

        if( $rule->operator == 'IN' ){
            foreach( $inputs as $input ){
                if( $input === $value ) 
                    return true;
            }
            return false;
        }

        if( $rule->operator == 'NOT_IN' ){
            foreach( $inputs as $input ){
                if( $input === $value ) 
                    return false;
            }
            return true;
        }

        return false;
    }

    /**
     * Normalize a referrer string
     * 
     */
    public function normalizeReferrer($referrer)
    {
        return parse_url(trim(strtolower($referrer), '/'), PHP_URL_HOST);
    }

    /**
     * Normalize a browser type
     * 
     */
    public function normalizeBrowserType($deviceType)
    {
        switch( $deviceType ){
            case 'Chrome Frame':
            case 'Headless Chrome':
            case 'Chrome':
            case 'Chrome Mobile iOS':
            case 'Chrome Mobile':
            case 'ChromePlus':
            case 'Chromium':
            case 'Chrome Webview':
                return 'CHROME';

            case 'Firefox Mobile iOS':
            case 'Firebird':
            case 'Firefox':
            case 'Firefox Focus':
            case 'Firefox Reality':
            case 'Firefox Rocket':
            case 'Firefox Mobile':
                return 'FIREFOX';

            case 'Internet Explorer':
            case 'IE':
            case 'IE Mobile': 
                return 'INTERNET_EXPLORER';

            case 'Microsoft Edge':
                return 'EDGE';

            case 'Mobile Safari':
            case 'Safari':
                return 'SAFARI';

            case 'Opera GX':
            case 'Opera Neon':
            case 'Opera Devices':
            case 'Opera Mini':
            case 'Opera Mobile':
            case 'Opera':
            case 'Opera Next':
            case 'Opera Touch':
                return 'OPERA';

            default: 
                return 'OTHER';
        }
    }

    /**
     * Normalize a device type
     * 
     */
    public function normalizeDeviceType($deviceType)
    {
        return strtoupper($deviceType);
    }

    /**
     * Determine if a visitor came in directy
     * 
     */
    public function isDirect($httpReferrer, $landingUrl)
    {
        return ! $httpReferrer;
    }

    /**
     * Determine if a visitor came from search without it being paid
     * 
     */
    public function isOrganic($httpReferrer, $entryURL, $mediumCsvList)
    {
        return $this->isSearch($httpReferrer) && !$this->isPaid($entryURL, $mediumCsvList);
    }

    /**
     * Determine if a visitor came from paid search/social etc
     * 
     * @param string $entryURL      The first URL the user visits
     * 
     * @return bool
     */
    public function isPaid($entryURL, $mediumCsvList)
    {
        $medium = $this->getParam($entryURL, $mediumCsvList);

        if( ! $medium ) return false;

        return in_array(strtolower($medium), ['cpc', 'ppc', 'cpa', 'cpm', 'cpv', 'cpp']);
    }

    /**
     * Determine is a user came from a search engine
     * 
     * Supports: Google, Yahoo, Bing
     * 
     * @param string $httpReferrer      The http referrer of the visitor
     */
    public function isSearch($httpReferrer = '')
    {
        $searchDomains = [
            'google.com',
            'www.google.com',
            'yahoo.com',
            'www.yahoo.com',
            'search.yahoo.com',
            'bing.com',
            'www.bing.com',
            'duckduckgo.com',
            'www.duckduckgo.com',
            'yandex.com',
            'www.yandex.com'
        ];

        $referrer = trim(strtolower($httpReferrer));
        $host     = parse_url($referrer, PHP_URL_HOST);
        
        return in_array($host, $searchDomains);
    }

    /**
     * Determine if a visit is a referral
     *
     * @param string $httpReferrer      The http referrer of the visitor 
     */
    public function isReferral($httpReferrer, $landingUrl)
    {
        return ! $this->isDirect($httpReferrer, $landingUrl) && ! $this->isSearch($httpReferrer);
    }

    public function getParam($landingUrl, $csvList)
    {
        $fields = explode(',', strtolower($csvList));
        $fields = array_map(function($field){ // Normalize fields
            return trim($field);
        }, $fields);

        $params = [];
        parse_str(parse_url($landingUrl, PHP_URL_QUERY), $_params);
        foreach( $_params as $prop => $param ){
            $params[strtolower(trim($prop))] = $param;
        }

        foreach( $fields as $field ){
            if( isset($params[$field]) )
                return $params[$field];
        }
        
        return null;
    }
}