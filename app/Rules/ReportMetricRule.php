<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class ReportMetricRule implements Rule
{
    protected $message = '';

    protected $request;

    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct($request)
    {
        $this->request = $request;
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
        if( ! $this->request->has('fields') ){
            $this->message = $attribute . ' requires the fields property to be set.';

            return false;
        }

        $fields = explode(',', $this->request->fields);
        if( ! in_array($value, $fields) ){
            $this->message = $attribute . ' not found in fields.';

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
        return $this->message;
    }
}
