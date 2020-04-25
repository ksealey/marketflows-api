<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class AutomationsRule implements Rule
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
        $automations = json_decode($value);
        if( ! is_array($automations) ){
            $this->message = $attribute . ' must be provided as a valid json array.';
            return false;
        }

        if( count($automations) > 4 ){
            $this->message = $attribute . ' cannot have more than 4 automations.';
            return false;
        }

        $automationTypes = ['EMAIL'];
        foreach( $automations as $idx => $automation ){
            if( empty($automation->type) ){
                $this->message = 'Automation at index ' . $idx . ' requires a type property.';
                return false;
            }

            if( ! in_array($automation->type, $automationTypes) ){
                $this->message = 'Automation at index ' . $idx . ' type ' . $automation->type . ' is invalid.';
                return false;
            }

            //  Email addresses
            if( $automation->type === 'EMAIL' ){
                if( empty($automation->email_addresses) ){
                    $this->message = 'Automation at index ' . $idx . ' requires an email_addresses property.';
                    return false;
                }

                if( ! is_array($automation->email_addresses) ){
                    $this->message = 'Automation at index ' . $idx . ' email_addresses must be provided as a valid json array.';
                    return false;
                }

                if( count($automation->email_addresses) > 20 ){
                    $this->message = 'Automation at index ' . $idx . ' cannot have more than 20 email addresses.';
                    return false;
                }

                foreach( $automation->email_addresses as $emailIdx => $emailAddress ){
                    if( ! is_string($emailAddress) || ! $emailAddress ){
                        $this->message = 'Automation at index ' . $idx . ', email address at index ' . $emailIdx. ' must be provided as a string.';
                        return false;
                    }

                    if( strlen($emailAddress) > 100 ){
                        $this->message = 'Automation at index ' . $idx . ', email address at index ' . $emailIdx. ' cannot exceed 100 characters.';
                        return false;
                    }

                    if( ! filter_var($emailAddress, FILTER_VALIDATE_EMAIL)) {
                        $this->message = 'Automation at index ' . $idx . ', email address at index ' . $emailIdx. ' must be a valid email address.';
                        return false;
                    }
                }
            }

            if( ! isset($automation->day_of_week) ){
                $this->message = 'Automation at index ' . $idx . ' requires a day_of_week property.';
                return false;
            }

            if( ! is_numeric($automation->day_of_week) || $automation->day_of_week < 1 || $automation->day_of_week > 7 ){
                $this->message = 'Automation at index ' . $idx . ' day_of_week must be a number between 1-7, with Monday being 1 and Sunday Being 7.';
                return false;
            }

            if( ! isset($automation->time) ){
                $this->message = 'Automation at index ' . $idx . ' requires a time property.';
                return false;
            }

            if( ! is_string($automation->time) || ! preg_match('/^(0[0-9]|1[0-9]|2[0-3]):[0-5][0-9](:00)?$/', $automation->time) ){
                $this->message = 'Automation at index ' . $idx . ' time must be a time string formatted as HH:mm using the 24 hour format - Ex, 14:30 for 2:30PM.';
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
