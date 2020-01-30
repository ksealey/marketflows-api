<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use App\Policies\Traits\HandlesCompanyResources;
use App\Models\Company;

class CompanyRule implements Rule
{
    protected $message = '';

    protected $user;

    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct($user)
    {
        $this->user = $user;
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
        //  Make sure the company exists and belongs to the account
        $company = Company::find($value);
        if( ! $company || $company->account_id != $this->user->account_id || ! $this->userCanViewCompany($this->user, $value) ){
            $this->message = 'Invalid company';

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
