<?php

namespace App\Rules\Company;

use Illuminate\Contracts\Validation\Rule;

class CampaignTargetRule implements Rule
{
    protected $message = 'Campaign target format invalid';

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
        //  Make sure the format is correct and v alues provided are allowed
        $target = json_decode($value);
        $config = config('validation.campaigns.campaign_targets');

        //
        //  Check device types
        //
        if( ! isset($target->DEVICE_TYPES) ){
            $this->message = 'Campaign target device types required';

            return false;
        }else if( ! is_array($target->DEVICE_TYPES) ){
            $this->message = 'Campaign target device types should be an array of device types';

            return false;
        }

        foreach( $target->DEVICE_TYPES as $deviceType ){
            if( ! in_array($deviceType, $config['device_types'] ) ){
                $this->message = 'Invalid device type ' . $deviceType;

                return false;
            }
        }

        //
        //  Check browsers
        //
        if( ! isset($target->BROWSERS) ){
            $this->message = 'Campaign target browsers required';

            return false;
        }else if( ! is_array($target->BROWSERS) ){
            $this->message = 'Campaign target browsers should be an array of browsers';

            return false;
        }

        foreach( $target->BROWSERS as $browser ){
            if( ! in_array($browser, $config['browsers'] ) ){
                $this->message = 'Invalid browser';

                return false;
            }
        }

        //
        //  Check locations
        //
        if( ! isset($target->LOCATIONS) ){
            $this->message = 'Campaign target locations required';

            return false;
        }else if( ! is_array($target->LOCATIONS) ){
            $this->message = 'Campaign target locations should be an array of locations';

            return false;
        }

        foreach( $target->LOCATIONS as $location ){
           if( ! isset($location->name) ){
                $this->message = 'All campaign target locations must have a name';

                return false;
           }

           if( ! isset($location->radius) || ! is_int($location->radius) ){
                $this->message = 'All campaign target locations must have a radius';

                return false;
            }

            if( ! isset($location->latitute) || ! is_numeric($location->latitute) ){
                $this->message = 'All campaign target locations must have a latitute';

                return false;
            }

            if( ! isset($location->longitute) || ! is_numeric($location->longitute) ){
                $this->message = 'All campaign target locations must have a longitute';

                return false;
            }
        }

        if( ! isset($target->URL_RULES) ){
            $this->message = 'Campaign target url rules required';

            return false;
        }else if( ! is_array($target->URL_RULES) ){
            $this->message = 'Campaign target url rules should be an array of url rules';

            return false;
        }

        foreach( $target->URL_RULES as $rule ){
            //  Name
            if( empty($rule->name) || ! is_string($rule->name) ){
                $this->message = 'All campaign target url rules must have a name';

                return false;
            }

            //  Driver
            if( empty($rule->driver) ){
                $this->message = 'All campaign target url rules must have a driver';

                return false;
            }elseif( ! in_array($rule->driver, $config['url_rules']['drivers']) ){
                $this->message = 'Invalid url rule driver';

                return false;
            }

            //  Type
            if( empty($rule->type) ){
                $this->message = 'All campaign target url rules must have a type';

                return false;
            }elseif( ! in_array($rule->type, $config['url_rules']['types']) ){
                $this->message = 'Invalid url rule type';

                return false;
            }

            //  Condition
            if( empty($rule->condition) ){
                $this->message = 'All campaign target url rules must have a condition';

                return false;
            }elseif( ! is_object($rule->condition) ){
                $this->message = 'Invalid url rule condition';

                return false;
            }

            if( empty($rule->condition->type) ){
                $this->message = 'All campaign target url rule conditions must have a type';

                return false;
            }

            if( ! in_array($rule->condition->type, $config['url_rules']['condition_types']) ){
                $this->message = 'Invalid campaign target url rule condition type';

                return false;
            }

            if( ! isset($rule->condition->key) || ! is_string($rule->condition->key) ){
                $this->message = 'All campaign target url rule conditions must have a key';

                return false;
            }

            if( ! isset($rule->condition->value) || ! is_string($rule->condition->value) ){
                $this->message = 'All campaign target url rule conditions must have a value';

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
