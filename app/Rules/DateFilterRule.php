<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use DateTime;
use Exception;

class DateFilterRule implements Rule
{
    protected $message = '';

    protected $dates = [
        'CURRENT_DAY',
        'YESTERDAY',
        'THIS_WEEK',
        'LAST_WEEK',
        'THIS_MONTH',
        'LAST_MONTH',
        'THIS_YEAR',
        'LAST_YEAR',
        'FIRST_DAY_OF',
        'LAST_DAY_OF',
        'FIRST_WEEK_OF',
        'LAST_WEEK_OF',
        'FIRST_MONTH_OF',
        'LAST_MONTH_OF',
        'PRIOR'
    ];

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
        $date = json_decode($value);
        if( ! $date ){
            $this->message = $attribute . ' must be a json string';

            return false;
        }

        if( empty($date->date) && empty($date->input) ){
            $this->message = $attribute . ' must have a date or input property';

            return false;
        }

        //  Date input
        if( ! empty($date->input) ){
            $dateInput = null;

            try{
                $dateInput = DateTime::createFromFormat('Y-m-d', $date->input);
            }catch(Exception $e){}

            if( ! $dateInput ){
                $this->message = 'Invalid date input';

                return false;
            }

            return true;
        }

        //  Date placeholders
        if( ! in_array($date->date, $this->dates) ){
            $this->message = 'Invalid date ' . $date->date;

            return false;
        }

        //  Validate intervals
        if( $this->hasInterval($date) ){
            if( empty($date->interval) ){
                $this->message = 'Interval required';

                return false;
            }

            if( ! $this->hasValidInterval($date) ){
                $this->message = 'Invalid interval';
    
                return false;
            }
        }

        return true;
    }


    public function hasInterval($date)
    {
        return in_array($date->date, [
            'THIS_WEEK',
            'LAST_WEEK',
            'FIRST_DAY_OF',
            'LAST_DAY_OF',
            'FIRST_WEEK_OF',
            'LAST_WEEK_OF',
            'FIRST_MONTH_OF',
            'LAST_MONTH_OF'
        ]);
    }

    public function hasValidInterval($date)
    {
        switch( $date->date ){
            case 'THIS_WEEK':
            case 'LAST_WEEK':
                return in_array($date->interval, [
                    'MONDAY', 
                    'TUESDAY', 
                    'WEDNESDAY', 
                    'THURSDAY', 
                    'FRIDAY', 
                    'SATURDAY', 
                    'SUNDAY'
                ]);
                
            case 'FIRST_DAY_OF':
            case 'LAST_DAY_OF':
            case 'FIRST_WEEK_OF':
            case 'LAST_WEEK_OF':
                return in_array($date->interval, [
                    'THIS_MONTH',
                    'LAST_MONTH',
                    'THIS_YEAR',
                    'LAST_YEAR'
                ]);

            case 'FIRST_MONTH_OF':
            case 'LAST_MONTH_OF':
                return in_array($date->interval, [
                    'THIS_YEAR',
                    'LAST_YEAR'
                ]);
            default:
                return false;
        }
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
