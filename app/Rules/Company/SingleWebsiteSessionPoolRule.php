<?php

namespace App\Rules\Company;

use Illuminate\Contracts\Validation\Rule;
use App\Models\Company\PhoneNumberPool;

class SingleWebsiteSessionPoolRule implements Rule
{
    protected $message = '';

    protected $company;
    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct($company)
    {
        $this->company = $company;
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
        $existingWebsitePool = PhoneNumberPool::where('company_id', $this->company->id)
                                        ->where('sub_category', 'WEBSITE_SESSION')
                                        ->first();
        if( $existingWebsitePool ){
            $this->message = 'You cannot have more than 1 website number pool per company';

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
