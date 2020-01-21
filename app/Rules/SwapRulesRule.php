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

            if( !is_array($swapRules->exclusion_rules ) ){
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

        $ruleTypes = [
            'LANDING_URL',
            'LANDING_PARAM'
        ];

        if( ! $isExclusionRule ){
            if( $rule->type === 'ALL' )
                return true;
        } 

        if( ! in_array($rule->type, $ruleTypes) )
            return false;

        if( empty($rule->operator) || ! is_string($rule->operator) )
            return false;

        $ruleOperators = [
            'EQUALS',
            'NOT_EQUALS',
            'CONTAINS',
            'NOT_CONTAINS'
        ];

        if( ! in_array($rule->operator, $ruleOperators) )
            return false;

        if( empty($rule->match_input) || ! is_object($rule->match_input) )
            return false;

        if( empty($rule->match_input->value) )
            return false;

        if( $rule->type == 'LANDING_PARAM' && empty($rule->match_input->key))
            return false;
        
        return true;
    }
}
