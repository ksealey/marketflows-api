<?php

namespace App\Rules\Company;

use Illuminate\Contracts\Validation\Rule;
use App\Models\Company\PhoneNumber;

class PhoneNumberRule implements Rule
{
    protected $companyId;

    protected $campaignId;

    protected $message;

    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct($companyId, $campaignId = null)
    {
        $this->companyId = $companyId;

        $this->campaignId = $campaignId;
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
        if( is_string($value) )
            $value = json_decode($value);
        
        if( ! is_array($value) ){
            $this->message = 'Phone numbers field should contain an array of phone number ids';

            return false;
        }

        $myPhones = PhoneNumber::where('company_id', $this->companyId)
                                ->whereIn('id', $value)
                                ->get();
        if( ! $myPhones ){
            $this->message = 'Phone numbers invalid';

            return false;
        }

        $foundIds  = array_column($myPhones->toArray(), 'id');
        
        $diff = array_diff($value, $foundIds);
        if( count($diff) ){
            $this->message = 'Phone numbers invalid | ' . implode(',', $diff);

            return false;
        }

        //  Make sure they are not in use
        $numbersInUse = PhoneNumber::numbersInUseExcludingCampaign($value, $this->campaignId);
        if( count($numbersInUse) ){
            $this->message = 'Phone numbers in use | ' . implode(',', $numbersInUse);

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
