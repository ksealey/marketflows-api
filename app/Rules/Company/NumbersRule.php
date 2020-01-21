<?php

namespace App\Rules\Company;

use Illuminate\Contracts\Validation\Rule;
use App\Models\Company\PhoneNumber;

class NumbersRule implements Rule
{
    protected $message;

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
        $numberIds = json_decode($value, true);
        if( ! $numberIds || ! is_array($numberIds) || empty($numberIds) ){
            $this->message = 'Numbers must be an array of number ids';

            return false;
        }

        $companyNumbers = PhoneNumber::where('company_id', $this->company->id)
                                      ->get()
                                      ->toArray();

        $companyNumberIds = array_column($companyNumbers, 'id');
        
        $invalidNumberIds = [];
        foreach( $numberIds as $numberId ){
            if( ! in_array($numberId, $companyNumberIds) )
                $invalidNumbers[] = $numberId;
        }

        if( count($invalidNumberIds) ){
            $this->message = 'Invalid numbers provided: ' . implode(', ', $invalidNumberIds);

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
