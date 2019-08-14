<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use App\Models\AudioClip;

class AudioClipRule implements Rule
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
        $audioClip = AudioClip::find($value);
        if( ! $audioClip || $audioClip->company_id != $this->companyId ){
            $this->message = 'Audio clip not found';

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
