<?php

namespace App\Http\Controllers\Company;

use Illuminate\Http\Request;
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
            'name'                       => 'bail|required|max:64',
            'forward_to_number'          => ['bail', 'required', 'regex:/(.*)[0-9]{3}(.*)[0-9]{3}(.*)[0-9]{4}(.*)/'],
            'greeting_enabled'           => 'bail|boolean',
            'keypress_enabled'           => 'bail|boolean',
            'whisper_enabled'            => 'bail|boolean',
            'recording_enabled'          => 'bail|boolean',
            'transcription_enabled'      => 'bail|boolean'
        ];

        $validator = validator($request->input(), $rules);

        $validator->sometimes('greeting_message_type', 'bail|required|in:TEXT,AUDIO', function($input){
            return $input->greeting_enabled;
        });
        $validator->sometimes('greeting_message', 'bail|required|max:128', function($input){
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
        $validator->sometimes('keypress_message_type', ['bail', 'required', 'in:TEXT,AUDIO'], function($input){
            return $input->keypress_enabled;
        });
        $validator->sometimes('keypress_message', ['bail', 'required', 'max:128'], function($input){
            return $input->keypress_enabled && $input->keypress_message_type === 'TEXT';
        });
        $validator->sometimes('keypress_audio_clip_id', ['bail', 'required', 'numeric',  new AudioClipRule($company->id)], function($input){
            return $input->keypress_enabled && $input->keypress_message_type === 'AUDIO';
        });

        $validator->sometimes('whisper_message', ['bail', 'required', 'max:128'], function($input){
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
            'greeting_message_type'     => $request->greeting_message_type,
            'greeting_audio_clip_id'    => $request->greeting_audio_clip_id,
            'greeting_message'          => $request->greeting_message,
            
            'keypress_enabled'          => !!$request->keypress_enabled,
            'keypress_key'              => $request->keypress_key,
            'keypress_attempts'         => $request->keypress_attempts,
            'keypress_timeout'          => $request->keypress_timeout,
            'keypress_message_type'     => $request->keypress_message_type,
            'keypress_audio_clip_id'    => $request->keypress_audio_clip_id,
            'keypress_message'          => $request->keypress_message,

            'whisper_enabled'           => !!$request->whisper_enabled,
            'whisper_message'           => $request->whisper_message,
            
            'recording_enabled'         => !!$request->recording_enabled,
            'transcription_enabled'     => !!$request->transcription_enabled,

            'created_by'                => $user->id,
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
            'whisper_enabled'            => 'bail|boolean',
            'recording_enabled'          => 'bail|boolean',
            'transcription_enabled'      => 'bail|boolean'
        ];

        $validator = validator($request->input(), $rules);

        $validator->sometimes('greeting_message_type', 'bail|required|in:TEXT,AUDIO', function($input){
            return $input->greeting_enabled;
        });
        $validator->sometimes('greeting_message', 'bail|required|max:128', function($input){
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
        $validator->sometimes('keypress_message_type', ['bail', 'required', 'in:TEXT,AUDIO'], function($input){
            return $input->keypress_enabled;
        });
        $validator->sometimes('keypress_message', ['bail', 'required', 'max:128'], function($input){
            return $input->keypress_enabled && $input->keypress_message_type === 'TEXT';
        });
        $validator->sometimes('keypress_audio_clip_id', ['bail', 'required', 'numeric',  new AudioClipRule($company->id)], function($input){
            return $input->keypress_enabled && $input->keypress_message_type === 'AUDIO';
        });

        $validator->sometimes('whisper_message', ['bail', 'required', 'max:128'], function($input){
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
        if( $request->filled('greeting_message') ){
            $phoneNumberConfig->greeting_message = $request->greeting_message;
        }
        if( $request->filled('greeting_audio_clip_id') ){
            $phoneNumberConfig->greeting_audio_clip_id = $request->greeting_audio_clip_id;
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
        if( $request->filled('keypress_message_type') ){
            $phoneNumberConfig->keypress_message_type = $request->keypress_message_type;
        }
        if( $request->filled('keypress_message') ){
            $phoneNumberConfig->keypress_message = $request->keypress_message;
        }
        if( $request->filled('keypress_audio_clip_id') ){
            $phoneNumberConfig->keypress_audio_clip_id = $request->keypress_audio_clip_id;
        }

        if( $request->filled('whisper_enabled') ){
            $phoneNumberConfig->whisper_enabled = !!$request->whisper_enabled;
        }
        if( $request->filled('whisper_message') ){
            $phoneNumberConfig->whisper_message = $request->whisper_message;
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

        if( $phoneNumberConfig->keypress_message_type !== 'AUDIO' ){
            $phoneNumberConfig->keypress_audio_clip_id = null;
        }

        $phoneNumberConfig->updated_by = $request->user()->id;
        $phoneNumberConfig->save();

        return response($phoneNumberConfig);
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
