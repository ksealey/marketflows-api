<?php

namespace App\Rules\Company;

use Illuminate\Contracts\Validation\Rule;
use App\Models\Company\Report;

class ReportMetricRule implements Rule
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
        if( ! Report::metricExists($this->module, $value) ){
            $this->message = $attribute . ' is invalid.';

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
