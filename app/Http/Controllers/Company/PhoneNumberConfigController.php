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
            'greeting_message'           => 'bail|nullable|max:128',
            'greeting_audio_clip_id'     => ['bail', 'nullable', 'numeric', new AudioClipRule($company->id)],
            'keypress_enabled'           => 'bail|boolean',
            'keypress_audio_clip_id'     => ['bail', 'nullable',  'numeric', new AudioClipRule($company->id)],
            'keypress_message'           => 'bail|nullable|max:128',
        ];

        $validator = Validator::make($request->input(), $rules);
        $validator->sometimes('keypress_key', 'bail|required|digits:1', function($input){
            return !!$input->keypress_enabled;
        });
        $validator->sometimes('keypress_attempts', 'bail|required|numeric|min:1|max:5', function($input){
            return !!$input->keypress_enabled;
        });
        $validator->sometimes('keypress_timeout', 'bail|required|numeric|min:5|max:60', function($input){
            return !!$input->keypress_enabled;
        });

        if( $validator->fails() ){
            return response([
                'error' => $validator->errors()->first()
            ], 400);
        }

        $user = $request->user();
        $phoneNumberConfig = PhoneNumberConfig::create([
            'company_id'                => $company->id,
            'name'                      => $request->name,
            'forward_to_number'         => $request->forward_to_number,
            'greeting_audio_clip_id'    => $request->greeting_audio_clip_id ?: null,
            'greeting_message'          => $request->greeting_message ?: null,
            'whisper_message'           => $request->whisper_message ?: null,
            'recording_enabled'         => !!$request->recording_enabled,
            'keypress_enabled'          => !!$request->keypress_enabled,
            'keypress_key'              => $request->filled('keypress_key') ? intval($request->keypress_key) : null,
            'keypress_attempts'         => intval($request->keypress_attempts) ?: 1,
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
            'greeting_message'           => 'bail|nullable|max:128',
            'greeting_audio_clip_id'     => ['bail', 'nullable', 'numeric', new AudioClipRule($company->id)],
            'keypress_enabled'           => 'bail|boolean',
            'keypress_audio_clip_id'     => ['bail', 'nullable',  'numeric', new AudioClipRule($company->id)],
            'keypress_message'           => 'bail|nullable|max:128',
        ];

        $validator = Validator::make($request->input(), $rules);
        $validator->sometimes('keypress_key', 'bail|required|digits:1', function($input) use($phoneNumberConfig){
            return !!$input->keypress_enabled && is_null($phoneNumberConfig->keypress_key);
        });
        $validator->sometimes('keypress_attempts', 'bail|required|numeric|min:1|max:5', function($input){
            return !!$input->keypress_enabled;
        });
        $validator->sometimes('keypress_timeout', 'bail|required|numeric|min:5|max:60', function($input){
            return !!$input->keypress_enabled;
        });

        if( $validator->fails() ){
            return response([
                'error' => $validator->errors()->first()
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
        if( $request->has('keypress_attempts') )
            $phoneNumberConfig->keypress_attempts = $request->keypress_attempts ?: null;
        if( $request->has('keypress_timeout') )
            $phoneNumberConfig->keypress_timeout = $request->keypress_timeout;
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
                'error' => 'This configuration is in use - Detach from all phone numbers and phone number pools then try again'
            ], 400);
        }

        $phoneNumberConfig->delete();

        return response([
            'message' => 'deleted'
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
