<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class SwapRulesRule implements Rule
{
    protected $message = '';

    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        $swapRules = json_decode($value);

        if( ! $swapRules || ! is_object($swapRules) ){
            $this->message = 'Swap rules must be a valid json object';

            return false;
        }

        //  Make sure there is at least 1 target and they're valid
        if( empty($swapRules->targets) || ! is_array($swapRules->targets) ){
            $this->message = 'Swap rules must contain at least 1 phone number matching target - see documentation for format';

            return false;
        }

        foreach( $swapRules->targets as $target ){
            if( ! is_string($target) ){
                $this->message = 'Swap rule targets must be strings';

                return false;
            }

            $targetData = preg_replace('/[^0-9\#]+/', '', $target);
            if( strlen($targetData) < 7 || strlen($targetData) > 13 ){
                $this->message = 'Swap rule target invalid';

                return false;
            }
        }

        //  Make sure there is at least 1 inclusion rule and it's valid
        if( empty($swapRules->inclusion_rules) || !is_array($swapRules->inclusion_rules) ){
            $this->message = 'Swap rules must contain at least 1 inclusion rule - see documentation for format';

            return false;
        }

        foreach( $swapRules->inclusion_rules as $groupIndex => $inclusionRuleGroup ){
            if( ! is_array($inclusionRuleGroup->rules) ){
                $this->message = 'Swap rule inclusion rules must be a list of rules. Group Index: ' . $groupIndex . ' - see documentation for format';
            
                return false;
            }

            foreach($inclusionRuleGroup->rules as $ruleIndex => $rule){
                if( ! $this->isValidRule($rule) ){
                    $this->message = 'Invalid inclusion rule found. Group Index: ' . $groupIndex. ', Rule index: ' . $ruleIndex . ' - see documentation for format';

                    return false;
                }
            }
        }

        //  When exclusion rules are provided, make sure that they're valid
        if( ! empty($swapRules->exclusion_rules) ){

            if( ! is_array($swapRules->exclusion_rules) ){
                $this->message = 'Swap rule exclusion rules must be a list of rules.  Group Index: ' . $groupIndex . ' - see documentation for format';
            
                return false;
            }

            foreach( $swapRules->exclusion_rules as $groupIndex => $exclusionRuleGroup ){
                if( ! is_array($exclusionRuleGroup->rules) ){
                    $this->message = 'Swap rule exclusion rules must be a list of rules. Group Index: ' . $groupIndex . ' - see documentation for format';
                
                    return false;
                }
    
                foreach($exclusionRuleGroup->rules as $ruleIndex => $rule){
                    if( ! $this->isValidRule($rule, true) ){
                        $this->message = 'Invalid exclusion rule found. Group Index: ' . $groupIndex. ', Rule index: ' . $ruleIndex . ' - see documentation for format';
    
                        return false;
                    }
                }
            }
        }

        //  Device Types
        if( empty($swapRules->device_types) ){
            $this->message = 'Swap rule device types required';
                
            return false;
        }

        if( ! is_array($swapRules->device_types) ){
            $this->message = 'Swap rule device types must be an array of device strings';
                
            return false;
        }

        foreach($swapRules->device_types as $idx => $deviceType){
            if( ! is_string($deviceType) ){
                $this->message = 'Swap rule device types must be strings - Index: ' . $idx . '.';
                
                return false;
            }

            if( ! in_array($deviceType, ['ALL', 'DESKTOP', 'TABLET', 'MOBILE']) ){
                $this->message = 'Swap rule device type invalid - Index ' . $idx;
                
                return false;
            }
        }

        //  Browser Types
        if( empty($swapRules->browser_types) ){
            $this->message = 'Swap rule browser types required';
                
            return false;
        }

        if( ! is_array($swapRules->browser_types) ){
            $this->message = 'Swap rule browser types must be an array of browser strings';
                
            return false;
        }

        foreach($swapRules->browser_types as $idx => $browserType){
            if( ! is_string($browserType) ){
                $this->message = 'Swap rule browser types must be strings - Index: ' . $idx;
                
                return false;
            }

            if( ! in_array($browserType, ['ALL', 'CHROME', 'FIREFOX', 'INTERNET_EXPLORER', 'EDGE', 'SAFARI', 'OPERA', 'OTHER']) ){
                $this->message = 'Swap rule browser type invalid - Index: ' . $idx;
                
                return false;
            }
        }
        
        return true;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return $this->message;
    }

    public function isValidRule($rule, $isExclusionRule = false)
    {
        if( empty($rule->type) || ! is_string($rule->type) )
            return false;

        if( ! $isExclusionRule ){
            if( $rule->type === 'ALL' )
                return true;
        } 

        //  Validate for types that do not need values
        if( in_array($rule->type, ['DIRECT', 'ORGANIC']) )
            return true;

        //
        //  Validate types that require operators and values have them
        //

        //  Check type
        if( ! in_array($rule->type, ['LANDING_PATH', 'LANDING_PARAM', 'REFERRER']) )
            return false;

        //  Check operator
        if( empty($rule->operator) || ! is_string($rule->operator) )
            return false;
            
        if( in_array($rule->operator, ['EMPTY', 'NOT_EMPTY']) ) // Validate operators that do not need values
            return true;

        if( ! in_array($rule->operator, ['EQUALS', 'NOT_EQUALS', 'CONTAINS', 'NOT_CONTAINS', 'MATCHES', 'NOT_MATCHES'] ) )
            return false;

        //  Check matchinput values
        if( empty($rule->match_input) || ! is_object($rule->match_input) )
            return false;

        if( empty($rule->match_input->value) || ! is_string($rule->match_input->value) )
            return false;

        //  parameters should also have a key
        if( $rule->type == 'LANDING_PARAM' && (empty($rule->match_input->key) || ! is_string($rule->match_input->key) ))
            return false;
        
        return true;
    }
}
