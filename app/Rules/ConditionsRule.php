<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class ConditionsRule implements Rule
{
    protected $message = '';

    protected $conditionFields = [];

    protected $operators = [
        'EMPTY',
        'NOT_EMPTY',
        'EQUALS',
        'NOT_EQUALS',
        'IN',
        'NOT_IN',
        'LIKE',
        'NOT_LIKE',
        'IS_TRUE',
        'IS_FALSE'
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
        $conditionGroups = json_decode($value);
        if( ! is_array($conditionGroups) ){
            $this->message = 'Condition groups must be a valid json array of condition arrays.';
            return false;
        }

        if( count($conditionGroups) > 20 ){
            $this->message = 'A maximum of 20 condition groups are allowed.';
            return false;
        }

        foreach( $conditionGroups as $groupIdx => $conditionGroup ){
            if( ! is_array($conditionGroup) ){
                $this->message = 'Condition group at index ' . $groupIdx . ' must be a valid json array of conditions.';
                return false;
            }
    
            if( count($conditionGroup) > 20 ){
                $this->message = 'A maximum of 20 conditions per group are allowed.';
                return false;
            }

            foreach( $conditionGroup as $idx => $condition ){
                if( empty($condition->field) ){
                    $this->message = 'Condition in group ' . $groupIdx . ' at index ' . $idx . ' requires a field property.';
                    return false;
                }

                if( ! in_array($condition->field, $this->conditionFields) ){
                    $this->message = 'Condition in group ' . $groupIdx . '  at index ' . $idx . ' field is invalid.';
                    return false;
                }

                if( empty($condition->operator) ){
                    $this->message = 'Condition in group ' . $groupIdx . '  at index ' . $idx . ' requires an operator property.';
                    return false;
                }

                if( ! in_array($condition->operator, $this->operators) ){
                    $this->message = 'Condition in group ' . $groupIdx . ' at index ' . $idx . ' operator is invalid.';
                    return false;
                }

                if( $condition->operator !== 'EMPTY' && $condition->operator !== 'NOT_EMPTY' && $condition->operator !== 'IS_TRUE' && $condition->operator !== 'IS_FALSE'){
                    if( empty($condition->inputs) ){
                        $this->message = 'Condition in group ' . $groupIdx . ' at index ' . $idx . ' requires an inputs property with input values.';
                        return false;
                    }

                    if( count($condition->inputs) > 20 ){
                        $this->message = 'Condition in group ' . $groupIdx . ' at index ' . $idx . ' cannot have more than 20 inputs.';
                        return false;
                    }

                    if( ! is_array($condition->inputs) ){
                        $this->message = 'Condition in group ' . $groupIdx . ' at index ' . $idx . ' inputs property must be an array.';
                        return false;
                    }

                    foreach( $condition->inputs as $inputIdx => $input ){
                        if( ! is_string($input) && ! is_numeric($input) ){
                            $this->message = 'Condition in group ' . $groupIdx . ' at index ' . $idx . ' inputs must only contain strings or numbers.';
                            return false;
                        }

                        if( strlen($input) > 255 ){
                            $this->message = 'Condition in group ' . $groupIdx . ' at index ' . $idx . ' inputs cannot contain more than 255 characters.';
                            return false;
                        }
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
