<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use App\Models\Plugin;

class PluginSettingsRule implements Rule
{
    public $message;
    public $pluginKey;

    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct($pluginKey)
    {
        $this->pluginKey = $pluginKey;
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
        $plugin = Plugin::where('key', $this->pluginKey)->first();
        
        if( $plugin->rules ){
            $validator = validator([$value], $plugin->rules);
            if( $validator->fails() ){
                $this->message = $validator->errors()->first;

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
