<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use App\Models\PhoneNumberPool;

class PhoneNumberPoolRule implements Rule
{
    protected $companyId;

    protected $message;

    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct($companyId)
    {
        $this->companyId = $companyId;
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
        $pool = PhoneNumberPool::find($value);

        if( $pool && ($pool->company_id == $this->companyId) )
            return true;

        $this->message = 'Invalid phone number pool';

        return false;
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
