<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use DateTime;
use Exception;

class DateRangeRule implements Rule
{
    protected $message = '';

    protected $dateKeys = [
        'TODAY',
        'YESTERDAY_TO_DATE',
        'LAST_7_DAYS',
        'LAST_30_DAYS',
        'LAST_4_WEEKS',
        'LAST_3_MONTHS',
        'WEEK_TO_DATE',
        'MONTH_TO_DATE',
        'YEAR_TO_DATE',
        'ALL_TIME',
        'CUSTOM'
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

        if( empty($date->key) ){
            $this->message = $attribute . ' must have a "key" property';

            return false;
        }

        if( ! in_array($date->key, $this->dateKeys) ){
            $this->message = $attribute . ' has an invalid "key" property';

            return false;
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
