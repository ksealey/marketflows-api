<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class ReportFieldsRule implements Rule
{
    protected $message = '';

    protected $fields = [
        'calls' => [
            'calls.company_id',
            'calls.phone_number_id',
            'phone_number_name',
            'calls.toll_free',
            'calls.category',
            'calls.sub_category',
            
            'calls.phone_number_pool_id',
            'phone_number_pool_name',
            'calls.session_id',

            'calls.direction',
            'calls.status',

            'calls.caller_first_name',
            'calls.caller_last_name',
            'calls.caller_country_code',
            'calls.caller_number',
            'calls.caller_city',
            'calls.caller_state',
            'calls.caller_zip',
            'calls.caller_country',
            
            'calls.dialed_country_code',
            'calls.dialed_number',
            'calls.dialed_city',
            'calls.dialed_state',
            'calls.dialed_zip',
            'calls.dialed_country',
            
            'calls.source',
            'calls.medium',
            'calls.content',
            'calls.campaign',

            'calls.recording_enabled',
            'calls.caller_id_enabled',
            'calls.forwarded_to',

            'calls.duration',
            'calls.created_at',
        ]
    ];

    protected $module;

    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct($module)
    {
        $this->module = $module;
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
        if( ! is_string($value) ){
            $this->message = $attribute . ' must be a string.';

            return false;
        }

        $fields = explode(',', $value);
        if( ! count($fields) ){
            $this->message = 'At least 1 field is required for ' . $attribute;

            return false;
        }

        if( count($fields) > 50 ){
            $this->message = 'No more than 50 fields allowed for ' . $attribute;

            return false;
        }

        if( ! $this->module ){
            $this->message = 'Module required for ' . $attribute;

            return false; 
        }

        foreach( $fields as $field ){
            if( ! in_array($field, $this->fields[$this->module]) ){
                $this->message = 'Invalid field ' . $field . ' provided for ' . $attribute;

                return false; 
            }
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
