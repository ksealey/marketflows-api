<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class RoleRule implements Rule
{
    protected $message = '';

    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
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
        $policyWrapper = json_decode(strtolower($value));
        if( ! $policyWrapper || empty($policyWrapper->policy) || ! is_array($policyWrapper->policy) )
            return false;
        
        foreach( $policyWrapper->policy as $rule ){
            if( empty($rule->module) 
                || ! is_string($rule->module) 
                ||  empty($rule->permissions) 
                || ! is_string($rule->permissions) 
            )
                return false;

            if( $rule->permissions === '*' ) // valid
                continue;

            $actions = explode(',', $rule->permissions);
            if( ! count($actions) )
                return false;

            foreach( $actions as $action ){
                if( ! in_array($action, ['create', 'read', 'update', 'delete']) )
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
        return 'Policy invalid';
    }
}
