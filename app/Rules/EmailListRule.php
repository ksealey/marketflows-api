<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class EmailListRule implements Rule
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
        $emails = explode(',', $value);
        if( ! count($emails) ){
            $this->message = 'No email addresses found';
            return false;
        }

        if( count($emails) > 10 ){
            $this->message = 'A maximum of 10 email addresses are allowed';
            return false;
        }

        foreach( $emails as $email ){
            $email = trim($email);
            if( ! filter_var($email, FILTER_VALIDATE_EMAIL) ){
                $this->message = 'Invalid email address found';
                return false;
            }

            if( strlen($email) > 100 ){
                $this->message = 'Email addresses cannot exceed 100 characters';
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
