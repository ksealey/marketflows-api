<?php

namespace App\Rules\Company;

use Illuminate\Contracts\Validation\Rule;
use \App\Models\Company\Report;

class ReportConditionsRule implements Rule
{
    protected $module;
    protected $message = '';
    protected $operators = [
        'equals',
        'not_equals',
        'like',
        'not_like',
        'in',
        'not_in',
        'matches',
        'does_not_match'
    ];

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
        $conditions = json_decode($value);
        if( ! is_array($conditions) ){
            $this->message = 'Conditions must be provided as a json array.';
            return false;
        }

        if( ! count($conditions) ){
            $this->message = 'At least 1 condition is required.';
            return false;
        }

        if( count($conditions) > 10 ){
            $this->message = 'A maximum of 10 conditions are allowed.';
            return false;
        }

        foreach( $conditions as $idx => $condition ){
            //
            //  field:operator:value
            //
            
            if( empty($condition->field) ){
                $this->message = 'Field required for condition at index ' . $idx . '.';
                return false;
            }

            if( empty($condition->operator) ){
                $this->message = 'Operator required for condition at index ' . $idx . '.';
                return false;
            }

            if( empty($condition->value)){
                $this->message = 'Value required for condition at index ' . $idx . '.';
                return false;
            }

            if( strlen($condition->value) > 128 ){
                $this->message = 'Value cannot exceed 128 characters for condition at index ' . $idx . '.';
                return false;
            }

            //  make sure field is valid
            if( ! Report::exposedFieldExists($this->module, $condition->field) ){
                $this->message = 'Field ' . $condition->field. ' invalid at index ' . $idx . '.';
                return false;
            }

            //  make sure operator is valid
            if( ! in_array($condition->operator, $this->operators) ){
                $this->message = 'Operator ' . $condition->operator . ' invalid at index ' . $idx . ' - Operators ARE case-sensitive.';
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
