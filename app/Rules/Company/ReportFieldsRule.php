<?php

namespace App\Rules\Company;

use Illuminate\Contracts\Validation\Rule;
use \App\Models\Company\Report;

class ReportFieldsRule implements Rule
{
    protected $message = '';

    protected $module;

    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct($module)
    {
        $this->module = $module;
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
            $this->message = $attribute . ' must be a string.';

            return false;
        }

        $fields = explode(',', $value);
        if( ! count($fields) ){
            $this->message = 'At least 1 field is required for ' . $attribute;

            return false;
        }

        if( count($fields) > 40 ){
            $this->message = 'No more than 40 fields allowed for ' . $attribute;

            return false;
        }

        if( ! $this->module ){
            $this->message = 'Module required for ' . $attribute;

            return false; 
        }

        foreach( $fields as $field ){
            if( ! Report::fieldExists($this->module, $field) ){
                $this->message = 'Invalid field ' . $field . ' provided for ' . $attribute;

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
