<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class ParamNameRule implements Rule
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
        if( ! is_string($value) ){
            $this->message  = $attribute . ' must be a string.';
            return false;
        }

        if( strlen($value) > 128 ){
            $this->message  = $attribute . ' cannot contain more than 128 characters.';
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
