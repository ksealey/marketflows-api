<?php

namespace App\Http\Controllers\Company;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Rules\Company\AudioClipRule;
use App\Models\Company;
use App\Models\Company\PhoneNumber;
use App\Models\Company\PhoneNumberConfig;
use Validator;
use DateTime;
use DateTimeZone;

class PhoneNumberConfigController extends Controller
{
    /**
     * List phone number configs
     * 
     */
    public function list(Request $request, Company $company)
    {
        $query = PhoneNumberConfig::where('company_id', $company->id);
        
        if( $request->search )
            $query->where(function($query) use($request){
                $query->where('name', 'like', '%' . $request->search . '%')
                      ->orWhere('forward_to_number', 'like', '%' . $request->search . '%');
            });

        return parent::results(
            $request,
            $query,
            [ 'order_by'  => 'in:name,created_at,forward_to_number,updated_at' ]
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
            'whisper_message'            => 'bail|max:128',
            'greeting_message'           => 'bail|max:128',
            'greeting_audio_clip_id'     => ['bail', 'numeric', new AudioClipRule($company->id)],
        ];

        $validator = Validator::make($request->input(), $rules);
        if( $validator->fails() ){
            return response([
                'error' => $validator->errors()->first()
            ], 400);
        }

        if( $request->greeting_message && $request->greeting_audio_clip_id )
            return response([
                'error' => 'You must either provide a greeting audio clip id or greeting message, but not both.'
            ], 400);

        $phoneNumberConfig = PhoneNumberConfig::create([
            'company_id'                => $company->id,
            'user_id'                   => $request->user()->id,
            'name'                      => $request->name,
            'forward_to_country_code'   => PhoneNumber::countryCode($request->forward_to_number),
            'forward_to_number'         => PhoneNumber::number($request->forward_to_number),
            'greeting_audio_clip_id'    => $request->greeting_audio_clip_id ?: null,
            'greeting_message'          => $request->greeting_message ?: null,
            'recording_enabled_at'      => $request->record ? now() : null,
            'caller_id_enabled_at'      => $request->caller_id ? now() : null,
            'whisper_message'           => $request->whisper_message ?: null
        ]);

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
            'whisper_message'            => 'bail|max:128',
            'greeting_message'           => 'bail|max:128',
            'greeting_audio_clip_id'     => ['bail', 'numeric', new AudioClipRule($company->id)],
        ];

        $validator = Validator::make($request->input(), $rules);
        if( $validator->fails() ){
            return response([
                'error' => $validator->errors()->first()
            ], 400);
        }

        if( $request->has('name') )
            $phoneNumberConfig->name = $request->name;
        if( $request->has('forward_to_country_code') )
            $phoneNumberConfig->forward_to_country_code = PhoneNumber::countryCode($request->forward_to_number);
        if( $request->has('forward_to_number') )
            $phoneNumberConfig->forward_to_number = PhoneNumber::number($request->forward_to_number);
        if( $request->has('greeting_audio_clip_id') )
            $phoneNumberConfig->greeting_audio_clip_id = $request->greeting_audio_clip_id;
        if( $request->has('greeting_message') )
            $phoneNumberConfig->greeting_message = $request->greeting_message;
        if( $request->has('record') )
            $phoneNumberConfig->recording_enabled_at = $request->record ? ( $phoneNumberConfig->recording_enabled_at ?: date('Y-m-d H:i:s') ) : null;
        if( $request->has('caller_id') )
            $phoneNumberConfig->caller_id_enabled_at = $request->caller_id ? ( $phoneNumberConfig->caller_id_enabled_at ?: date('Y-m-d H:i:s') ) : null;
        if( $request->has('whisper_message') )
            $phoneNumberConfig->whisper_message = $request->whisper_message;
        
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
}
