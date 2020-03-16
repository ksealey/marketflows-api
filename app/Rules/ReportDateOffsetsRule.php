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
        $dateRangeOffsets = explode(',', $value);
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

            if( $offset < 0 ){
                $this->message = $attribute . ' cannot contain values less than 0.';

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
