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
                $this->message = 'Invalid browser ' . $browser;

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
