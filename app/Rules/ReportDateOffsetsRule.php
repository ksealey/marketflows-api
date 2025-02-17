<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class ReportDateOffsetsRule implements Rule
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
        $dateRangeOffsets = json_decode($value);

        if( ! is_array($dateRangeOffsets) ){
            $this->message = $attribute . ' must be provided as a json array.';
            return false;
        }

        if( ! count($dateRangeOffsets) ){
            $this->message = $attribute . ' must have at least one offset.';
            return false;
        }

        if( count($dateRangeOffsets) > 4 ){
            $this->message = $attribute . ' cannot have more than 4 offsets.';
            return false;
        }

        $foundOffsets = [];
        foreach( $dateRangeOffsets as $offset ){
            $offset = trim($offset);

            if( in_array($offset, $foundOffsets) ){
                $this->message = $attribute . ' cannot contain duplicate values.';

                return false;
            }
    
            if( ! is_numeric($offset) ){
                $this->message = $attribute . ' must have numeric values.';

                return false;
            }

            if( $offset < 1 ){
                $this->message = $attribute . ' cannot contain values less than 1.';

                return false;
            }

            if( $offset > 999 ){
                $this->message = $attribute . ' cannot contain values more than 999.';

                return false;
            }

            $foundOffsets[] = $offset;
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
