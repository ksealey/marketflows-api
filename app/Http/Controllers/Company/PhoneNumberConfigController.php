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
    /**
     * List phone number configs
     * 
     */
    public function list(Request $request, Company $company)
    {
        $rules = [
            'order_by' => 'in:phone_number_configs.name,phone_number_configs.created_at,phone_number_configs.updated_at,phone_number_configs.forward_to_number',
        ];

        $query = DB::table('phone_number_configs')
                    ->where('phone_number_configs.company_id', $company->id)
                    ->whereNull('phone_number_configs.deleted_at');

        $searchFields = [
            'phone_number_configs.name',
            'phone_number_configs.forward_to_number'
        ];
       
        return parent::results(
            $request,
            $query,
            $rules,
            $searchFields,
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
            'record'                     => 'bail|boolean',
            'caller_id'                  => 'bail|boolean',
            'whisper_message'            => 'bail|nullable|max:128',
            'greeting_message'           => 'bail|nullable|max:128',
            'greeting_audio_clip_id'     => ['bail', 'nullable', 'numeric', new AudioClipRule($company->id)],
            'keypress_enabled'           => 'bail|boolean',
            'keypress_timeout'           => '',
            'keypress_audio_clip_id'     => ['bail', 'nullable',  'numeric', new AudioClipRule($company->id)],
            'keypress_message'           => 'bail|nullable|max:128',
        ];

        $validator = Validator::make($request->input(), $rules);
        $validator->sometimes('keypress_key', 'bail|required|numeric|min:0|max:9', function($input){
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

        if( $request->greeting_message && $request->greeting_audio_clip_id )
            return response([
                'error' => 'You must either provide a greeting audio clip id or greeting message, but not both.'
            ], 400);

        $user = $request->user();
        $phoneNumberConfig = PhoneNumberConfig::create([
            'company_id'                => $company->id,
            'user_id'                   => $user->id,
            'name'                      => $request->name,
            'forward_to_country_code'   => PhoneNumber::countryCode($request->forward_to_number) ?: null,
            'forward_to_number'         => PhoneNumber::number($request->forward_to_number),
            'greeting_audio_clip_id'    => $request->greeting_audio_clip_id ?: null,
            'greeting_message'          => $request->greeting_message ?: null,
            'recording_enabled_at'      => $request->record ? now() : null,
            'caller_id_enabled_at'      => $request->caller_id ? now() : null,
            'whisper_message'           => $request->whisper_message ?: null,
            'keypress_enabled_at'       => $request->keypress_enabled ? now() : null,
            'keypress_key'              => intval($request->keypress_key) ?: null,
            'keypress_attempts'         => intval($request->keypress_attempts) ?: null,
            'keypress_timeout'          => intval($request->keypress_timeout) ?: null,
            'keypress_audio_clip_id'    => $request->keypress_audio_clip_id ?: null,
            'keypress_message'          => $request->keypress_message ?: null,
        ]);

        event(new PhoneNumberConfigEvent($user, [$phoneNumberConfig], 'create'));

        return response($phoneNumberConfig, 201);
    }

    /**
     * Read a phone number config
     * 
     */
    public function read(Request $request, Company $company, PhoneNumberConfig $phoneNumberConfig)
    {
        $phoneNumberConfig->phone_number_pools = $phoneNumberConfig->phone_number_pools;

        $phoneNumberConfig->phone_numbers      = $phoneNumberConfig->phone_numbers;

        return response( $phoneNumberConfig );
    }

    /**
     * Update a phone number config
     * 
     */
    public function update(Request $request, Company $company, PhoneNumberConfig $phoneNumberConfig)
    {
        $rules = [
            'name'                       => 'bail|max:64',
            'forward_to_number'          => 'bail|digits_between:10,13',
            'record'                     => 'bail|boolean',
            'caller_id'                  => 'bail|boolean',
            'whisper_message'            => 'bail|nullable|max:128',
            'greeting_message'           => 'bail|nullable|max:128',
            'greeting_audio_clip_id'     => ['bail', 'nullable', 'numeric', new AudioClipRule($company->id)],
            'keypress_enabled'           => 'bail|boolean',
            'keypress_audio_clip_id'     => ['bail', 'nullable',  'numeric', new AudioClipRule($company->id)],
            'keypress_message'           => 'bail|nullable|max:128',
        ];

        $validator = Validator::make($request->input(), $rules);
        $validator->sometimes('keypress_key', 'bail|required|numeric|min:0|max:9', function($input){
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

        if( $request->filled('name') )
            $phoneNumberConfig->name = $request->name;
        if( $request->filled('forward_to_number') ){
            $phoneNumberConfig->forward_to_country_code = PhoneNumber::countryCode($request->forward_to_number) ?: null;
            $phoneNumberConfig->forward_to_number       = PhoneNumber::number($request->forward_to_number);
        }
        if( $request->filled('greeting_audio_clip_id') )
            $phoneNumberConfig->greeting_audio_clip_id = $request->greeting_audio_clip_id;
        if( $request->filled('greeting_message') )
            $phoneNumberConfig->greeting_message = $request->greeting_message;
        if( $request->filled('record') )
            $phoneNumberConfig->recording_enabled_at = $request->record ? ( $phoneNumberConfig->recording_enabled_at ?: date('Y-m-d H:i:s') ) : null;
        if( $request->filled('caller_id') )
            $phoneNumberConfig->caller_id_enabled_at = $request->caller_id ? ( $phoneNumberConfig->caller_id_enabled_at ?: date('Y-m-d H:i:s') ) : null;
        if( $request->filled('whisper_message') )
            $phoneNumberConfig->whisper_message = $request->whisper_message;
        if( $request->filled('keypress_enabled') )
            $phoneNumberConfig->keypress_enabled_at = $request->keypress_enabled ? ($request->keypress_enabled_at ?: now()) : null;
        if( $request->filled('keypress_key') )
            $phoneNumberConfig->keypress_key = intval($request->keypress_key);
        if( $request->filled('keypress_attempts') )
            $phoneNumberConfig->keypress_attempts = intval($request->keypress_attempts);
        if( $request->filled('keypress_timeout') )
            $phoneNumberConfig->keypress_timeout = intval($request->keypress_timeout);
        if( $request->filled('keypress_audio_clip_id') )
            $phoneNumberConfig->keypress_audio_clip_id = $request->keypress_audio_clip_id;
        if( $request->filled('keypress_message') )
            $phoneNumberConfig->keypress_message = $request->keypress_message;
    
        $phoneNumberConfig->save();

        event(new PhoneNumberConfigEvent($user, [$phoneNumberConfig], 'update'));

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

        event(new PhoneNumberConfigEvent($user, [$phoneNumberConfig], 'delete'));

        return response([
            'message' => 'deleted'
        ]);
    }
}
