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
            'forward_to_number'          => 'bail|required|numeric|digits_between:10,13',
            'recording_enabled'          => 'bail|boolean',
            'whisper_message'            => 'bail|nullable|max:128',
            'greeting_enabled'           => 'bail|boolean',
            'greeting_message'           => 'bail|nullable|max:128',
            'greeting_audio_clip_id'     => ['bail', 'nullable', 'numeric', new AudioClipRule($company->id)],
            'keypress_enabled'           => 'bail|boolean',
            'keypress_key'               => 'bail|digits:1',
            'keypress_attempts'          => 'bail|numeric|min:1|max:10',
            'keypress_timeout'           => 'bail|numeric|min:5|max:60'
        ];

        $validator = validator($request->input(), $rules);
        if( $validator->fails() ){
            return response([
                'error' => $validator->errors()->first()
            ], 400);
        }

        //
        //  Require message or audio clip when greeting enabled
        //
        if( $request->greeting_enabled && ! $request->greeting_message && ! $request->greeting_audio_clip_id ){
            return response([
                'error' => 'When greetings are enable you must either provide a greeting message or a greeting audio clip id'
            ], 400);
        }

        //
        //  Require message or audio clip when keypress enabled
        //
        if( $request->keypress_enabled && ! $request->keypress_message && ! $request->keypress_audio_clip_id ){
            return response([
                'error' => 'When keypresses are enable you must either provide a keypress message or a keyress audio clip id'
            ], 400);
        }

        $user = $request->user();
        $phoneNumberConfig = PhoneNumberConfig::create([
            'account_id'                => $company->account_id,
            'company_id'                => $company->id,
            'name'                      => $request->name,
            'forward_to_number'         => $request->forward_to_number,
            'greeting_audio_clip_id'    => $request->greeting_audio_clip_id ?: null,
            'greeting_message'          => $request->greeting_message ?: null,
            'whisper_message'           => $request->whisper_message ?: null,
            'recording_enabled'         => !!$request->recording_enabled,
            'keypress_enabled'          => !!$request->keypress_enabled,
            'keypress_key'              => $request->filled('keypress_key') ? intval($request->keypress_key) : 1,
            'keypress_attempts'         => intval($request->keypress_attempts) ?: 3,
            'keypress_timeout'          => intval($request->keypress_timeout)  ?: 10,
            'keypress_audio_clip_id'    => $request->keypress_audio_clip_id ?: null,
            'keypress_message'          => $request->keypress_message ?: null,
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
            'name'                       => 'bail|min:1|max:64',
            'forward_to_number'          => 'bail|numeric|digits_between:10,13',
            'recording_enabled'          => 'bail|boolean',
            'whisper_message'            => 'bail|nullable|max:128',
            'greeting_enabled'           => 'bail|boolean',
            'greeting_message'           => 'bail|nullable|max:128',
            'greeting_audio_clip_id'     => ['bail', 'nullable', 'numeric', new AudioClipRule($company->id)],
            'keypress_enabled'           => 'bail|boolean',
            'keypress_key'               => 'bail|digits:1',
            'keypress_attempts'          => 'bail|numeric|min:1|max:10',
            'keypress_timeout'           => 'bail|numeric|min:5|max:60'
        ];

        $validator =validator($request->input(), $rules);   
        if( $validator->fails() ){
            return response([
                'error' => $validator->errors()->first()
            ], 400);
        }

        //
        //  Require message or audio clip when greeting enabled
        //
        if( $request->greeting_enabled && ! $request->greeting_message && ! $request->greeting_audio_clip_id && ! $phoneNumberConfig->greeting_message && ! $phoneNumberConfig->greeting_audio_clip_id){
            return response([
                'error' => 'When greetings are enable you must either provide a greeting message or a greeting audio clip id'
            ], 400);
        }

        //
        //  Require message or audio clip when keypress enabled
        //
        if( $request->keypress_enabled && ! $request->keypress_message && ! $request->keypress_audio_clip_id && ! $phoneNumberConfig->keypress_message && ! $phoneNumberConfig->keypress_audio_clip_id){
            return response([
                'error' => 'When keypresses are enable you must either provide a keypress message or a keyress audio clip id'
            ], 400);
        }

        if( $request->has('name') )
            $phoneNumberConfig->name = $request->name;
        if( $request->has('forward_to_number') )
            $phoneNumberConfig->forward_to_number = $request->forward_to_number;
        if( $request->has('greeting_audio_clip_id') )
            $phoneNumberConfig->greeting_audio_clip_id = $request->greeting_audio_clip_id ?: null;
        if( $request->has('greeting_message') )
            $phoneNumberConfig->greeting_message = $request->greeting_message ?: null;
        if( $request->has('recording_enabled') )
            $phoneNumberConfig->recording_enabled = !!$request->recording_enabled;
        if( $request->has('whisper_message') )
            $phoneNumberConfig->whisper_message = $request->whisper_message ?: null;
        if( $request->has('keypress_enabled') )
            $phoneNumberConfig->keypress_enabled = !!$request->keypress_enabled;
        if( $request->has('keypress_key') )
            $phoneNumberConfig->keypress_key = $request->keypress_key;
        if( $request->filled('keypress_attempts') )
            $phoneNumberConfig->keypress_attempts = intval($request->keypress_attempts);
        if( $request->has('keypress_timeout') )
            $phoneNumberConfig->keypress_timeout = intval($request->keypress_timeout);
        if( $request->has('keypress_audio_clip_id') )
            $phoneNumberConfig->keypress_audio_clip_id = $request->keypress_audio_clip_id ?: null;
        if( $request->has('keypress_message') )
            $phoneNumberConfig->keypress_message = $request->keypress_message ?: null;

        $phoneNumberConfig->save();

        return response( $phoneNumberConfig );
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
