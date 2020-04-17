<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class ConditionsRule implements Rule
{
    protected $message = '';

    protected $conditionFields = [];

    protected $operators = [
        'EQUALS',
        'NOT_EQUALS',
        'IN',
        'NOT_IN',
        'EMPTY',
        'NOT_EMPTY',
        'LIKE',
        'NOT_LIKE'
    ];

    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct($conditionFields)
    {
        $this->conditionFields = $conditionFields;
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
            $this->message = $attribute . ' must be a valid json array.';
            return false;
        }

        if( count($conditions) > 100 ){
            $this->message = 'A maximum of 100 conditions are allowed.';
            return false;
        }

        foreach( $conditions as $idx => $condition ){
            if( empty($condition->field) ){
                $this->message = 'Condition at index ' . $idx . ' requires a field property.';
                return false;
            }

            if( ! in_array($condition->field, $this->conditionFields) ){
                $this->message = 'Condition at index ' . $idx . ' field is invalid.';
                return false;
            }

            if( empty($condition->operator) ){
                $this->message = 'Condition at index ' . $idx . ' requires an operator property.';
                return false;
            }

            if( ! in_array($condition->operator, $this->operators) ){
                $this->message = 'Condition at index ' . $idx . ' operator is invalid.';
                return false;
            }

            if( $condition->operator !== 'EMPTY' && $condition->operator !== 'NOT_EMPTY' ){
                if( empty($condition->inputs) ){
                    $this->message = 'Condition at index ' . $idx . ' requires an inputs property with input values.';
                    return false;
                }

                if( ! is_array($condition->inputs) ){
                    $this->message = 'Condition at index ' . $idx . ' inputs property must be an array.';
                    return false;
                }

                foreach( $condition->inputs as $inputIdx => $input ){
                    if( ! is_string($input) && ! is_numeric($input) ){
                        $this->message = 'Condition at index ' . $idx . ' inputs must only contain strings or numbers.';
                        return false;
                    }
                }
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
