<?php

namespace App\Rules\Company;

use Illuminate\Contracts\Validation\Rule;

class CampaignNumberSwapRule implements Rule
{
    protected $message = 'Campaign number swap rule invalid';

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        $swapRule = json_decode($value);

        //  Make sure it has a number format
        if( empty($swapRule->number_format) || ! is_string($swapRule->number_format) ){
            $this->message = 'Campaign number swap rule must contain a number format';

            return false;
        }

        //  Make sure the number format is valid
        preg_match_all('/\#/', $swapRule->number_format, $matches);
        
        $digitCount = count($matches[0]);
        if( $digitCount < 10 ){
            $this->message = 'Campaign number swap rule number format must contain at least 10 digit placeholders (#)';

            return false;
        }

        if( $digitCount > 13 ){
            $this->message = 'Campaign number swap rule number format cannot have more than 13 digit placeholders (#)';

            return false;
        }


        //  Check condition types
        if( empty($swapRule->conditions) )
            return true;

        if( ! is_array($swapRule->conditions) ){
            $this->message = 'Campaign number swap rule conditions must be an array of conditions';

            return false;
        }

        $config = config('validation.campaigns.campaign_number_swaps');

        foreach( $swapRule->conditions as $condition ){
            if( empty($condition->type) ){
                $this->message = 'All campaign number swap rule conditions must have a type field';

                return false;
            }

            if( ! in_array($condition->type, $config['condition_types']) ){
                $this->message = 'Invalid campaign number swap rule condition type found';

                return false;
            }

            if( ! isset($condition->value) ){
                $this->message = 'All campaign number swap rule conditions must have a value field';

                return false;
            }

            if( !empty($condition->value) && ! is_string($condition->value) ){
                $this->message = 'All campaign number swap rule condition values must be a string';

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
}
