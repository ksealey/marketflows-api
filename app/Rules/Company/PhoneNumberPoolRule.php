<?php

namespace App\Rules\Company;

use Illuminate\Contracts\Validation\Rule;
use App\Models\Company;
use App\Models\Company\Campaign;
use App\Models\Company\PhoneNumberPool;

class PhoneNumberPoolRule implements Rule
{
    protected $company;

    protected $message;

    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct(Company $company)
    {
        $this->company  = $company;
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

        if( ! $pool ){
            $this->message = 'Phone number pool does not exist';

            return false;
        }

        if( $pool->company_id != $this->company->id ){
            $this->message = 'Phone number pool invalid';

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
