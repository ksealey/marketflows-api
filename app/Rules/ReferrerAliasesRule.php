<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class ReferrerAliasesRule implements Rule
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
        $aliases = json_decode($value);

        if( ! $aliases || empty($aliases->aliases) ){
            $this->message = 'Referrer aliases must be an array of alias objects';
       
            return false;
        }

        foreach($aliases->aliases as $idx=>$alias){
            if( empty($alias->domain) ){
                $this->message = 'Referrer alias does not have a domain value at index ' . $idx;

                return false;
            }

            if( empty($alias->alias) ){
                $this->message = 'Referrer alias does not have an alias value at index ' . $idx;

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
