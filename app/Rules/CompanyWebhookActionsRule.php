<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class CompanyWebhookActionsRule implements Rule
{
    public $message;

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
        $webhookActions  = json_decode( $value, true );

        $actionsProvided = array_keys($webhookActions);

        $config = config('validation.webhook_actions');
        foreach( $actionsProvided as $action ){
            if( ! in_array($action, $config['actions'] ) ){
                $this->message = 'Invalid webhook action';

                return false;
            }
        }

        foreach( $webhookActions as $webhookAction ){
            if( ! isset($webhookAction['url']) ){
                $this->message = 'All webhook actions must have a url field';

                return false;
            }

            if( ! empty($webhookAction['url']) && ! is_string($webhookAction['url']) ){
                $this->message = 'All webhook actions urls must be a string';

                return false;
            }

            if( empty($webhookAction['method']) ){
                $this->message = 'All webhook actions must have a method';

                return false;
            }

            $method         = $webhookAction['method'];
            $methodsAllowed = ['POST', 'GET'];
            if( ! is_string($method) || ! in_array($method, $methodsAllowed) ){
                $this->message = 'All webhook actions must have a method of POST or GET';

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
