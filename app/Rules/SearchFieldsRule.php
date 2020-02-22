<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class SearchFieldsRule implements Rule
{
    protected $searchFields = [];

    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct($searchFields = [])
    {
        $this->searchFields = $searchFields;
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
        if( ! $value )
            return true;
            
        if( ! is_string($value) )
            return false;

        $searchFields  = explode(',', $value);
        if( ! count($searchFields) )
            return false;

        foreach( $searchFields as $searchField ){
            if( ! is_string($searchField) )
                return false;

            if( ! in_array($searchField, $this->searchFields ) )
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
        return 'Search fields invalid';
    }
}
