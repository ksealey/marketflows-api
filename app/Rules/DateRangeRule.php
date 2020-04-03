<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use DateTime;
use Exception;

class DateRangeRule implements Rule
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
        $dateRange = json_decode($value, true);
        $validator = validator($dateRange, [
            'start' => 'nullable|date_format:Y-m-d',
            'end'   => 'nullable|date_format:Y-m-d'
        ]);

        if( $validator->fails() ){
            $this->message = $validator->errors()->first();
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
