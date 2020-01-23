<?php
namespace App\Traits;

trait CanSwapNumbers
{
    public function shouldSwap($entryURL)
    {
        if( ! $this->swap_rules )
            return false;
        
        $swapRules = json_decode(json_encode($this->swap_rules));

        $shouldSwap = false;

        //  See if it should be included
        foreach( $swapRules->inclusion_rules as $ruleGroup ){
            //  Make sure the entire group passes or it fails
            if( $this->ruleGroupPasses($ruleGroup, $entryURL) ){
                $shouldSwap = true;

                break; // No need to check the other rules since one passed
            }
        }

        //  
        //  If we have determined that the page should have a swap,
        //  Make sure that the exlcusion rules do not pass 
        //
        if( $shouldSwap && ! empty($swapRules->exclusion_rules) ){
            foreach( $swapRules->exclusion_rules as $ruleGroup ){
                if( $this->ruleGroupPasses($ruleGroup, $entryURL) ){
                    $shouldSwap = false;

                    break; // No need to check the other rules since one passed
                }
            }
        }

        return $shouldSwap;
    }

    /**
     * Determine if an entire rule group passes
     * 
     */
    public function ruleGroupPasses($ruleGroup, $entryURL)
    {
        $groupPassed = true;

        foreach( $ruleGroup->rules as $rule ){
            if( ! $this->rulePasses($rule, $entryURL) ){
                $groupPassed = false;
            
                break; // No need to check the other rules since one failed
            }
        }

        return $groupPassed;
    }

    /**
     * Determine if a single rule passes
     * 
     */
    public function rulePasses($rule, $entryURL)
    {
        if( $rule->type == 'ALL' )
            return true;
        
        $matchInput = $rule->match_input;
        if( $rule->type == 'LANDING_PATH' ){
            $value = strtolower(trim(parse_url($entryURL, PHP_URL_PATH), '/'));
            $input = strtolower(trim(trim($matchInput->value, ' '), '/'));
        }

        if( $rule->type == 'LANDING_PARAM' ){
            //  Pull parameters from url
            parse_str(parse_url($entryURL, PHP_URL_QUERY), $params);
            
            //  Nothing to do here if the param does not exist
            if( empty($params[$matchInput->key]) )
                return false;

            $value = strtolower($params[$matchInput->key]);
            $input = strtolower(trim($matchInput->value, ' ')); 
        }

        if( $rule->operator == 'EQUALS' )
            return $input == $value;

        if( $rule->operator == 'NOT_EQUALS' )
            return $input != $value;

        if( $rule->operator == 'CONTAINS' )
            return stripos($value, $input) !== false;

        if( $rule->operator == 'NOT_CONTAINS' )
            return stripos($value, $input) === false;
        
        
        return false;
    }

    public function targets()
    {
        if( ! $this->swap_rules )
            return [];
        
        $swapRules = json_decode(json_encode($this->swap_rules));
        
        return !empty($swapRules->targets) ? $swapRules->targets : [];

    }
}