<?php

namespace App\Rules\Company;

use Illuminate\Contracts\Validation\Rule;
use App\Models\Company;
use App\Models\Company\Campaign;
use App\Models\Company\PhoneNumber;

class PhoneNumberRule implements Rule
{
    protected $company;

    protected $campaign;

    protected $message;

    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct(Company $company, Campaign $campaign)
    {
        $this->company  = $company;

        $this->campaign = $campaign;
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
        $myPhones = PhoneNumber::where('company_id', $this->company->id)
                                ->whereIn('id', $value)
                                ->get();
        if( ! $myPhones ){
            $this->message = 'The phone numbers provided are invalid';

            return false;
        }

        $foundIds  = array_column($myPhones->toArray(), 'id');
        
        $diff = array_diff($value, $foundIds);
        if( count($diff) ){
            $this->message = 'Some of the phone numbers provided are invalid.';

            return false;
        }

        //  Make sure they are not in use
        $numbersInUse = PhoneNumber::numbersInUse($value, $this->campaign->id);
        if( count($numbersInUse) ){
            $this->message = 'Some of the numbers provided are already in use.';

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
