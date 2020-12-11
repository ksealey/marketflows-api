<?php

namespace App\Http\Controllers\Company;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Http\Controllers\Controller;
use App\Rules\Company\AudioClipRule;
use App\Models\Company;
use App\Models\Company\PhoneNumber;
use App\Models\Company\PhoneNumberConfig;
use App\Events\Company\PhoneNumberConfigEvent;
use Validator;
use DateTime;
use DateTimeZone;
use DB;

class PhoneNumberConfigController extends Controller
{
    static $fields = [
        'phone_number_configs.name',
        'phone_number_configs.forward_to_number',
        'phone_number_configs.greeting_enabled',
        'phone_number_configs.recording_enabled',
        'phone_number_configs.transcription_enabled',
        'phone_number_configs.keypress_enabled',
        'phone_number_configs.whisper_enabled',
        'phone_number_configs.keypress_conversion_enabled',
        'phone_number_configs.keypress_qualification_enabled',
        'phone_number_configs.created_at',
        'phone_number_configs.updated_at'
    ];

    /**
     * List phone number configs
     * 
     */
    public function list(Request $request, Company $company)
    {
        $query = PhoneNumberConfig::where('phone_number_configs.company_id', $company->id);

        return parent::results(
            $request,
            $query,
            [],
            self::$fields,
            'phone_number_configs.created_at'
        );
    }

    /**
     * Create a phone number config
     * 
     */
    public function create(Request $request, Company $company)
    {
       $rules = [
            'name'                                  => 'bail|required|max:64',
            'forward_to_number'                     => ['bail', 'required', 'regex:/(.*)[0-9]{3}(.*)[0-9]{3}(.*)[0-9]{4}(.*)/'],
            'greeting_enabled'                      => 'bail|boolean',
            'keypress_enabled'                      => 'bail|boolean',
            'keypress_directions_message'           => ['bail', Rule::requiredIf(function() use($request){ return !!$request->keypress_enabled; }), 'max:255'],
            'keypress_error_message'                => ['bail', Rule::requiredIf(function() use($request){ return !!$request->keypress_enabled; }), 'max:255'],
            'keypress_success_message'              => ['bail', 'nullable', 'max:255'],
            'keypress_failure_message'              => ['bail', 'nullable', 'max:255'],
            'whisper_enabled'                       => 'bail|boolean',
            'recording_enabled'                     => 'bail|boolean',
            'transcription_enabled'                 => 'bail|boolean',
            'keypress_conversion_enabled'           => 'bail|boolean',
            'keypress_qualification_enabled'        => 'bail|boolean',
            'keypress_conversion_key_converted'     => ['bail', Rule::requiredIf(function() use($request){ return !!$request->keypress_conversion_enabled; }), 'digits:1', 'different:keypress_conversion_key_unconverted'],
            'keypress_conversion_key_unconverted'   => ['bail', Rule::requiredIf(function() use($request){ return !!$request->keypress_conversion_enabled; }), 'digits:1', 'different:keypress_conversion_key_converted'],
            'keypress_conversion_attempts'          => ['bail', Rule::requiredIf(function() use($request){ return !!$request->keypress_conversion_enabled; }), 'numeric', 'min:1', 'max:10'],
            'keypress_conversion_timeout'           => ['bail', Rule::requiredIf(function() use($request){ return !!$request->keypress_conversion_enabled; }), 'numeric', 'min:5', 'max:30'],
            'keypress_conversion_directions_message'=> ['bail', Rule::requiredIf(function() use($request){ return !!$request->keypress_conversion_enabled; }), 'max:255'],
            'keypress_conversion_error_message'     => ['bail', Rule::requiredIf(function() use($request){ return !!$request->keypress_conversion_enabled; }), 'max:255'],
            'keypress_conversion_success_message'   => ['bail', 'nullable', 'max:255'],
            'keypress_conversion_failure_message'   => ['bail', 'nullable', 'max:255'],
            'keypress_qualification_key_qualified'  => ['bail', Rule::requiredIf(function() use($request){ return !!$request->keypress_qualification_enabled; }), 'digits:1', 'different:keypress_qualification_key_potential,keypress_qualification_key_customer,keypress_qualification_key_unqualified'],
            'keypress_qualification_key_potential'  => ['bail', Rule::requiredIf(function() use($request){ return !!$request->keypress_qualification_enabled; }), 'digits:1', 'different:keypress_qualification_key_qualified,keypress_qualification_key_customer,keypress_qualification_key_unqualified'],
            'keypress_qualification_key_customer'   => ['bail', Rule::requiredIf(function() use($request){ return !!$request->keypress_qualification_enabled; }), 'digits:1', 'different:keypress_qualification_key_qualified,keypress_qualification_key_potential,keypress_qualification_key_unqualified'],
            'keypress_qualification_key_unqualified'=> ['bail', Rule::requiredIf(function() use($request){ return !!$request->keypress_qualification_enabled; }), 'digits:1', 'different:keypress_qualification_key_qualified,keypress_qualification_key_potential,keypress_qualification_key_customer'],
            'keypress_qualification_attempts'       => ['bail', Rule::requiredIf(function() use($request){ return !!$request->keypress_qualification_enabled; }), 'numeric', 'min:1', 'max:10'],
            'keypress_qualification_timeout'        => ['bail', Rule::requiredIf(function() use($request){ return !!$request->keypress_qualification_enabled; }), 'numeric', 'min:5', 'max:30'],
            'keypress_qualification_directions_message' => ['bail', Rule::requiredIf(function() use($request){ return !!$request->keypress_qualification_enabled; }), 'max:255'],
            'keypress_qualification_error_message'      => ['bail', Rule::requiredIf(function() use($request){ return !!$request->keypress_qualification_enabled; }), 'max:255'],
            'keypress_qualification_success_message'    => ['bail', 'nullable', 'max:255'],
            'keypress_qualification_failure_message'    => ['bail', 'nullable', 'max:255']
        ];

        $validator = validator($request->input(), $rules);

        $validator->sometimes('greeting_message_type', 'bail|required|in:TEXT,AUDIO', function($input){
            return $input->greeting_enabled;
        });
        $validator->sometimes('greeting_message', 'bail|required|max:255', function($input){
            return $input->greeting_enabled && $input->greeting_message_type === 'TEXT';
        });
        $validator->sometimes('greeting_audio_clip_id', ['bail', 'required', 'numeric',  new AudioClipRule($company->id)], function($input){
            return $input->greeting_enabled && $input->greeting_message_type === 'AUDIO';
        });

        $validator->sometimes('keypress_key', ['bail', 'required', 'digits:1'], function($input){
            return $input->keypress_enabled;
        });
        $validator->sometimes('keypress_attempts', ['bail', 'required', 'numeric', 'min:1', 'max:10'], function($input){
            return $input->keypress_enabled;
        });
        $validator->sometimes('keypress_timeout', ['bail', 'required', 'numeric', 'min:5', 'max:30'], function($input){
            return $input->keypress_enabled;
        });

        $validator->sometimes('whisper_message', ['bail', 'required', 'max:255'], function($input){
            return $input->whisper_enabled;
        });

        if( $validator->fails() ){
            return response([
                'error' => $validator->errors()->first()
            ], 400);
        }

        $user = $request->user();
        $phoneNumberConfig = PhoneNumberConfig::create([
            'account_id'                => $company->account_id,
            'company_id'                => $company->id,
            'name'                      => $request->name,

            'forward_to_number'         => preg_replace('/[^0-9]+/', '',$request->forward_to_number),

            'greeting_enabled'          => !!$request->greeting_enabled,
            'greeting_message_type'     => $request->greeting_message_type ?: 'TEXT',
            'greeting_message'          => $request->greeting_message ?: null,
            'greeting_audio_clip_id'    => $request->greeting_audio_clip_id,

            'keypress_enabled'          => !!$request->keypress_enabled,
            'keypress_key'              => $request->filled('keypress_key') ? $request->keypress_key : 1,
            'keypress_attempts'         => $request->filled('keypress_attempts') ? $request->keypress_attempts : 3,
            'keypress_timeout'          => $request->filled('keypress_timeout') ? $request->keypress_timeout : 10,
            'keypress_directions_message' => $request->filled('keypress_directions_message') ? $request->keypress_directions_message : null,
            'keypress_error_message'     => $request->filled('keypress_error_message') ? $request->keypress_error_message : null,
            'keypress_success_message'   => $request->filled('keypress_success_message') ? $request->keypress_success_message : null,
            'keypress_failure_message'   => $request->filled('keypress_failure_message') ? $request->keypress_failure_message : null,

            'whisper_enabled'           => !!$request->whisper_enabled,
            'whisper_message'           => $request->whisper_message ?: null,
            
            'recording_enabled'         => !!$request->recording_enabled,
            'transcription_enabled'     => !!$request->transcription_enabled,

            'keypress_conversion_enabled'           => !!$request->keypress_conversion_enabled,
            'keypress_conversion_key_converted'     => $request->filled('keypress_conversion_key_converted') ? $request->keypress_conversion_key_converted : 1,
            'keypress_conversion_key_unconverted'   => $request->filled('keypress_conversion_key_unconverted') ? $request->keypress_conversion_key_unconverted : 2,
            'keypress_conversion_attempts'          => $request->filled('keypress_conversion_attempts') ? $request->keypress_conversion_attempts : 3,
            'keypress_conversion_timeout'           => $request->filled('keypress_conversion_timeout') ? $request->keypress_conversion_timeout : 10,
            'keypress_conversion_directions_message'=> $request->keypress_conversion_directions_message ?: null,
            'keypress_conversion_error_message'     => $request->keypress_conversion_error_message ?: null,
            'keypress_conversion_success_message'   => $request->keypress_conversion_success_message ?: null,
            'keypress_conversion_failure_message'   => $request->keypress_conversion_failure_message ?: null,

            'keypress_qualification_enabled'                => !!$request->keypress_qualification_enabled,
            'keypress_qualification_key_qualified'          => $request->filled('keypress_qualification_key_qualified') ? $request->keypress_qualification_key_qualified : 1,
            'keypress_qualification_key_potential'          => $request->filled('keypress_qualification_key_potential') ? $request->keypress_qualification_key_potential : 2,
            'keypress_qualification_key_customer'           => $request->filled('keypress_qualification_key_customer') ? $request->keypress_qualification_key_customer : 3,
            'keypress_qualification_key_unqualified'        => $request->filled('keypress_qualification_key_unqualified') ? $request->keypress_qualification_key_unqualified : 4,
            'keypress_qualification_attempts'               => $request->filled('keypress_qualification_attempts') ? $request->keypress_qualification_attempts : 3,
            'keypress_qualification_timeout'                => $request->filled('keypress_qualification_timeout') ? $request->keypress_qualification_timeout : 10,
            'keypress_qualification_directions_message'     => $request->keypress_qualification_directions_message ?: null,
            'keypress_qualification_error_message'          => $request->keypress_qualification_error_message ?: null,
            'keypress_qualification_success_message'        => $request->keypress_qualification_success_message ?: null,
            'keypress_qualification_failure_message'        => $request->keypress_qualification_failure_message ?: null,

            'created_by' => $user->id
        ]);

        return response($phoneNumberConfig, 201);
    }

    /**
     * Read a phone number config
     * 
     */
    public function read(Request $request, Company $company, PhoneNumberConfig $phoneNumberConfig)
    {
        return response($phoneNumberConfig);
    }

    /**
     * Update a phone number config
     * 
     */
    public function update(Request $request, Company $company, PhoneNumberConfig $phoneNumberConfig)
    {
        $rules = [
            'name'                       => 'bail|max:64',
            'forward_to_number'          => 'bail|numeric|digits_between:10,13',
            'greeting_enabled'           => 'bail|boolean',
            'keypress_enabled'           => 'bail|boolean',
            'keypress_directions_message'           => ['bail', Rule::requiredIf(function() use($request){ return !!$request->keypress_enabled; }), 'max:255'],
            'keypress_error_message'                => ['bail', Rule::requiredIf(function() use($request){ return !!$request->keypress_enabled; }), 'max:255'],
            'keypress_success_message'              => ['bail', 'nullable', 'max:255'],
            'keypress_failure_message'              => ['bail', 'nullable', 'max:255'],
            
            'whisper_enabled'            => 'bail|boolean',
            'recording_enabled'          => 'bail|boolean',
            'transcription_enabled'      => 'bail|boolean',
            'keypress_conversion_enabled'   => 'bail|boolean',
            'keypress_qualification_enabled'=> 'bail|boolean',
            'keypress_conversion_key_converted'     => ['bail', Rule::requiredIf(function() use($request){ return !!$request->keypress_conversion_enabled; }), 'digits:1', 'different:keypress_conversion_key_unconverted'],
            'keypress_conversion_key_unconverted'   => ['bail', Rule::requiredIf(function() use($request){ return !!$request->keypress_conversion_enabled; }), 'digits:1', 'different:keypress_conversion_key_converted'],
            'keypress_conversion_attempts'          => ['bail', Rule::requiredIf(function() use($request){ return !!$request->keypress_conversion_enabled; }), 'numeric', 'min:1', 'max:10'],
            'keypress_conversion_timeout'           => ['bail', Rule::requiredIf(function() use($request){ return !!$request->keypress_conversion_enabled; }), 'numeric', 'min:5', 'max:30'],
            'keypress_conversion_directions_message'=> ['bail', Rule::requiredIf(function() use($request){ return !!$request->keypress_conversion_enabled; }), 'max:255'],
            'keypress_conversion_error_message'     => ['bail', Rule::requiredIf(function() use($request){ return !!$request->keypress_conversion_enabled; }), 'max:255'],
            'keypress_conversion_success_message'   => ['bail', 'nullable', 'max:255'],
            'keypress_conversion_failure_message'   => ['bail', 'nullable', 'max:255'],
            'keypress_qualification_key_qualified'  => ['bail', Rule::requiredIf(function() use($request){ return !!$request->keypress_qualification_enabled; }), 'digits:1', 'different:keypress_qualification_key_potential,keypress_qualification_key_customer,keypress_qualification_key_unqualified'],
            'keypress_qualification_key_potential'  => ['bail', Rule::requiredIf(function() use($request){ return !!$request->keypress_qualification_enabled; }), 'digits:1', 'different:keypress_qualification_key_qualified,keypress_qualification_key_customer,keypress_qualification_key_unqualified'],
            'keypress_qualification_key_customer'   => ['bail', Rule::requiredIf(function() use($request){ return !!$request->keypress_qualification_enabled; }), 'digits:1', 'different:keypress_qualification_key_qualified,keypress_qualification_key_potential,keypress_qualification_key_unqualified'],
            'keypress_qualification_key_unqualified'=> ['bail', Rule::requiredIf(function() use($request){ return !!$request->keypress_qualification_enabled; }), 'digits:1', 'different:keypress_qualification_key_qualified,keypress_qualification_key_potential,keypress_qualification_key_customer'],
            'keypress_qualification_attempts'       => ['bail', Rule::requiredIf(function() use($request){ return !!$request->keypress_qualification_enabled; }), 'numeric', 'min:1', 'max:10'],
            'keypress_qualification_timeout'        => ['bail', Rule::requiredIf(function() use($request){ return !!$request->keypress_qualification_enabled; }), 'numeric', 'min:5', 'max:30'],
            'keypress_qualification_directions_message' => ['bail', Rule::requiredIf(function() use($request){ return !!$request->keypress_qualification_enabled; }), 'max:255'],
            'keypress_qualification_error_message'      => ['bail', Rule::requiredIf(function() use($request){ return !!$request->keypress_qualification_enabled; }), 'max:255'],
            'keypress_qualification_success_message'    => ['bail', 'nullable', 'max:255'],
            'keypress_qualification_failure_message'    => ['bail', 'nullable', 'max:255']
        ];

        $validator = validator($request->input(), $rules);

        $validator->sometimes('greeting_message_type', 'bail|required|in:TEXT,AUDIO', function($input){
            return $input->greeting_enabled;
        });
        $validator->sometimes('greeting_message', 'bail|required|max:255', function($input){
            return $input->greeting_enabled && $input->greeting_message_type === 'TEXT';
        });
        $validator->sometimes('greeting_audio_clip_id', ['bail', 'required', 'numeric',  new AudioClipRule($company->id)], function($input){
            return $input->greeting_enabled && $input->greeting_message_type === 'AUDIO';
        });

        $validator->sometimes('keypress_key', ['bail', 'required', 'digits:1'], function($input){
            return $input->keypress_enabled;
        });
        $validator->sometimes('keypress_attempts', ['bail', 'required', 'numeric', 'min:1', 'max:10'], function($input){
            return $input->keypress_enabled;
        });
        $validator->sometimes('keypress_timeout', ['bail', 'required', 'numeric', 'min:5', 'max:30'], function($input){
            return $input->keypress_enabled;
        });

        $validator->sometimes('whisper_message', ['bail', 'required', 'max:255'], function($input){
            return $input->whisper_enabled;
        });

        if( $validator->fails() ){
            return response([
                'error' => $validator->errors()->first()
            ], 400);
        }

        if( $request->filled('name') ){
            $phoneNumberConfig->name = $request->name;
        }

        if( $request->filled('forward_to_number') ){
            $phoneNumberConfig->forward_to_number = $request->forward_to_number;
        }

        if( $request->filled('greeting_enabled') ){
            $phoneNumberConfig->greeting_enabled = !!$request->greeting_enabled;
        }
        if( $request->filled('greeting_message_type') ){
            $phoneNumberConfig->greeting_message_type = $request->greeting_message_type;
        }
        if( $request->has('greeting_message') ){
            $phoneNumberConfig->greeting_message = $request->greeting_message ?: null;
        }
        if( $request->has('greeting_audio_clip_id') ){
            $phoneNumberConfig->greeting_audio_clip_id = $request->greeting_audio_clip_id ?: null;
        }

        if( $request->filled('keypress_enabled') ){
            $phoneNumberConfig->keypress_enabled = !!$request->keypress_enabled;
        }
        if( $request->filled('keypress_key') ){
            $phoneNumberConfig->keypress_key = $request->keypress_key;
        }
        if( $request->filled('keypress_attempts') ){
            $phoneNumberConfig->keypress_attempts = $request->keypress_attempts;
        }
        if( $request->filled('keypress_timeout') ){
            $phoneNumberConfig->keypress_timeout = $request->keypress_timeout;
        }
        if( $request->has('keypress_directions_message') ){
            $phoneNumberConfig->keypress_directions_message = $request->keypress_directions_message ?: null;
        }
        if( $request->has('keypress_error_message') ){
            $phoneNumberConfig->keypress_error_message = $request->keypress_error_message ?: null;
        }
        if( $request->has('keypress_success_message') ){
            $phoneNumberConfig->keypress_success_message = $request->keypress_success_message ?: null;
        }
        if( $request->has('keypress_failure_message') ){
            $phoneNumberConfig->keypress_failure_message = $request->keypress_failure_message ?: null;
        }
    
        if( $request->filled('keypress_conversion_enabled') ){
            $phoneNumberConfig->keypress_conversion_enabled = !!$request->keypress_conversion_enabled;
        }
        if( $request->filled('keypress_conversion_key_converted') ){
            $phoneNumberConfig->keypress_conversion_key_converted = $request->keypress_conversion_key_converted;
        }
        if( $request->filled('keypress_conversion_key_unconverted') ){
            $phoneNumberConfig->keypress_conversion_key_unconverted = $request->keypress_conversion_key_unconverted;
        }
        if( $request->filled('keypress_conversion_attempts') ){
            $phoneNumberConfig->keypress_conversion_attempts = $request->keypress_conversion_attempts;
        }
        if( $request->filled('keypress_conversion_timeout') ){
            $phoneNumberConfig->keypress_conversion_timeout = $request->keypress_conversion_timeout;
        }
        if( $request->has('keypress_conversion_directions_message') ){
            $phoneNumberConfig->keypress_conversion_directions_message = $request->keypress_conversion_directions_message ?: null;
        }
        if( $request->has('keypress_conversion_error_message') ){
            $phoneNumberConfig->keypress_conversion_error_message = $request->keypress_conversion_error_message ?: null;
        }
        if( $request->has('keypress_conversion_success_message') ){
            $phoneNumberConfig->keypress_conversion_success_message = $request->keypress_conversion_success_message ?: null;
        }
        if( $request->has('keypress_conversion_failure_message') ){
            $phoneNumberConfig->keypress_conversion_failure_message = $request->keypress_conversion_failure_message ?: null;
        }


        if( $request->filled('keypress_qualification_enabled') ){
            $phoneNumberConfig->keypress_qualification_enabled = !!$request->keypress_qualification_enabled;
        }
        if( $request->filled('keypress_qualification_key_qualified') ){
            $phoneNumberConfig->keypress_qualification_key_qualified = $request->keypress_qualification_key_qualified;
        }
        if( $request->filled('keypress_qualification_key_potential') ){
            $phoneNumberConfig->keypress_qualification_key_potential = $request->keypress_qualification_key_potential;
        }
        if( $request->filled('keypress_qualification_key_customer') ){
            $phoneNumberConfig->keypress_qualification_key_customer = $request->keypress_qualification_key_customer;
        }
        if( $request->filled('keypress_qualification_key_unqualified') ){
            $phoneNumberConfig->keypress_qualification_key_unqualified = $request->keypress_qualification_key_unqualified;
        }
        if( $request->filled('keypress_qualification_attempts') ){
            $phoneNumberConfig->keypress_qualification_attempts = $request->keypress_qualification_attempts;
        }
        if( $request->filled('keypress_qualification_timeout') ){
            $phoneNumberConfig->keypress_qualification_timeout = $request->keypress_qualification_timeout;
        }
        if( $request->has('keypress_qualification_directions_message') ){
            $phoneNumberConfig->keypress_qualification_directions_message = $request->keypress_qualification_directions_message ?: null;
        }
        if( $request->has('keypress_qualification_error_message') ){
            $phoneNumberConfig->keypress_qualification_error_message = $request->keypress_qualification_error_message ?: null;
        }
        if( $request->has('keypress_qualification_success_message') ){
            $phoneNumberConfig->keypress_qualification_success_message = $request->keypress_qualification_success_message ?: null;
        }
        if( $request->has('keypress_qualification_failure_message') ){
            $phoneNumberConfig->keypress_qualification_failure_message = $request->keypress_qualification_failure_message ?: null;
        }

        if( $request->filled('whisper_enabled') ){
            $phoneNumberConfig->whisper_enabled = !!$request->whisper_enabled;
        }
        if( $request->has('whisper_message') ){
            $phoneNumberConfig->whisper_message = $request->whisper_message ?: null;
        }

        if( $request->filled('recording_enabled') ){
            $phoneNumberConfig->recording_enabled = !!$request->recording_enabled;
        }

        if( $request->filled('transcription_enabled') ){
            $phoneNumberConfig->transcription_enabled = !!$request->transcription_enabled;
        }

        //
        //  Cleanup
        //
        if( $phoneNumberConfig->greeting_message_type !== 'AUDIO' ){
            $phoneNumberConfig->greeting_audio_clip_id = null;
        }

        $phoneNumberConfig->updated_by = $request->user()->id;
        $phoneNumberConfig->save();

        return response($phoneNumberConfig);
    }

    /**
     * Clone a phone number config
     * 
     */
    public function clone(Request $request, Company $company, PhoneNumberConfig $phoneNumberConfig)
    {
        $validator = validator($request->input(), [
            'name' => 'bail|required|max:64'
        ]);
        if( $validator->fails() ){
            return response([
                'error' => $validator->errors()->first()
            ], 400);
        }

        $copyData = $phoneNumberConfig->toArray();

        $copyData['name'] = $request->name;
        $copyData['created_by'] = $request->user()->id;
        
        unset($copyData['id']);
        unset($copyData['created_at']);
        unset($copyData['updated_at']);
        unset($copyData['updated_by']);

        $copy = PhoneNumberConfig::create($copyData);
        
        return response($copy, 201);
    }

    /**
     * Delete a phone number config
     * 
     */
    public function delete(Request $request, Company $company, PhoneNumberConfig $phoneNumberConfig)
    {
        if( $phoneNumberConfig->isInUse() ){
            return response([
                'error' => 'This configuration is in use - Detach from all phone numbers and try again'
            ], 400);
        }

        $phoneNumberConfig->delete();

        return response([
            'message' => 'Deleted'
        ]);
    }

    /**
     * Export results
     * 
     */
    public function export(Request $request, Company $company)
    {
        $request->merge([
            'company_id'   => $company->id,
            'company_name' => $company->name
        ]);

        return parent::exportResults(
            PhoneNumberConfig::class,
            $request,
            [],
            self::$fields,
            'phone_number_configs.created_at'
        );
    }
}
