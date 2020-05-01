<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use \App\Models\Company;

class CompanyListRule implements Rule
{
    protected $message = '';

    protected $accountId;
    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct($accountId)
    {
        $this->accountId = $accountId;
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
        //
        //  Make sure the ids are all in the DB
        //
        $companyIds = json_decode($value);
        array_unique($companyIds);

        if( ! count($companyIds) ){
            $this->message = 'at least one valid company id required';
            return false;
        }

        foreach( $companyIds as $companyId ){
            if( ! is_numeric($companyId) ){
                $this->message = 'company ids must be numeric';
                return false;
            }
        }

        $count = Company::where('account_id', $this->accountId)
                        ->whereIn('id', $companyIds)
                        ->count();

        if( $count != count($companyIds) ){
            $this->message = 'invalid company ids found';
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
