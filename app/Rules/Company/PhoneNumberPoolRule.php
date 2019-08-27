<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use App\Models\Company\PhoneNumberPool;

class PhoneNumberPoolRule implements Rule
{
    protected $companyId;

    protected $campaignId;

    protected $message;

    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct(int $companyId, $campaignId = null)
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
        $pool = PhoneNumberPool::find($value);

        if( ! $pool ){
            $this->message = 'Phone number pool does not exist';

            return false;
        }

        if( $pool->company_id != $this->companyId ){
            $this->message = 'Phone number pool invalid';

            return false;
        }

        if( $pool->isInUseExcludingCampaign($this->campaignId) ){
            $this->message = 'Phone number pool in use';

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
