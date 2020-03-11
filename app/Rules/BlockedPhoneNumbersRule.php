<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use App\Models\Account;
use App\Models\Company;
use Validator;

class BlockedPhoneNumbersRule implements Rule
{
    protected $message = '';

    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct()
    {
        
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
        $numbers = json_decode($value, true);
        if( ! is_array($numbers) || ! count($numbers) ){
            $this->message = 'Blocked numbers must be an array of number objects';

            return false;
        }

        if( count($numbers) > 40 ){
            $this->message = 'Only 40 blocked numbers can be added at a time'; 

            return false;
        }   

        foreach( $numbers as $idx => $number ){
            $validator = Validator::make($number, [
                'name'      => 'required',
                'number'    => 'required|digits_between:10,13',
            ]);

            if( $validator->fails() ){
                $this->message = 'Blocked number at index ' . $idx . ' failed - ' . $validator->errors()->first();

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
