<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class DateComparisonRule implements Rule
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
        $comparisons = json_decode($value);
        if( ! is_array($comparisons) ){
            $this->message = 'Date comparisons must ba a json array of integers.';
            return false;
        }

        if( count($comparisons) > 3 ){
            $this->message = 'Date comparisons cannot contain more than 3 values.';
            return false;
        }

        foreach( $comparisons as $idx => $comparison ){
            if( ! is_int($comparison) ){
                $this->message = 'Date comparison at index ' . $idx . ' must be an integer.';
                return false;
            }
            if( $comparison < 1 || $comparison > 3 ){
                $this->message = 'Date comparison at index ' . $idx . ' must be a number between 1 and 3.';
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
