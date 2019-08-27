<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Rules\PhoneNumberPoolRule;
use App\Rules\Company\AudioClipRule;
use App\Models\Company\AudioClip;
use App\Models\PhoneNumber;
use Validator;
use Exception;

class PhoneNumberController extends Controller
{
    /**
     * List phone numbers
     * 
     */
    public function list(Request $request)
    {
        $rules = [
            'start' => 'numeric',
            'limit' => 'numeric',
        ];

        $validator = Validator::make($request->input(), $rules);
        if( $validator->fails() ){
            return response([
                'error' =>  $validator->errors()->first()
            ], 400);
        }

        $user  = $request->user();
        $query = PhoneNumber::where('company_id', $user->company_id);
        if( $search = $request->search ){
            $query->where(function($query) use($search){
                $query->where('name', 'like', $search . '%')
                      ->orWhere('number', 'like', $search . '%')
                      ->orWhere('source', 'like', $search . '%')
                      ->orWhere('forward_to_number', 'like', $search . '%');
            });
        }

        $totalCount = $query->count();
        
        $query->offset($request->start ?: 0);
        $query->limit($request->limit ?: 25);

        $phoneNumbers = $query->get();

        return response([
            'phone_numbers' => $phoneNumbers,
            'result_count'  => count($phoneNumbers),
            'total_count'   => $totalCount,
            'message'       => 'success'
        ]);
    }

    /**
     * Create a phone number
     * 
     */
    public function create(Request $request)
    {
        $config = config('services.twilio');
        $user   = $request->user();

        $rules = [
            'phone_number_pool' => ['bail', new PhoneNumberPoolRule($user->company_id)],
            'number'            => 'bail|required|digits_between:10,13',
            'name'              => 'bail|required|max:255',
            'source'            => 'bail|required|max:255',
            'forward_to_number' => 'bail|required|digits_between:10,13',
            'audio_clip'        => ['bail', 'numeric', new AudioClipRule($user->company_id)],
            'record'            => 'boolean',
            'whisper_message'   => 'max:255',
            'whisper_language'  => 'in:' . implode(',', array_keys($config['languages'])),
            'whisper_voice'     => 'in:' . implode(',', array_keys($config['voices'])),
        ];

        $validator = Validator::make($request->input(), $rules);
        if( $validator->fails() ){
            return response([
                'error' => $validator->errors()->first()
            ], 400);
        }

        //  Purchase a phone number
        try{
            $numData = PhoneNumber::purchase($request->number);
            $can     = $numData['capabilities'];

            $phoneNumber = PhoneNumber::create([
                'company_id'                => $user->company_id,
                'created_by'                => $user->id,
                'twilio_id'                 => $numData['sid'],
                'country_code'              => $numData['country_code'],
                'number'                    => $numData['number'],
                'voice'                     => $can['voice'],
                'sms'                       => $can['sms'],
                'mms'                       => $can['mms'],
                'phone_number_pool_id'      => $request->phone_number_pool,
                'name'                      => $request->name,
                'source'                    => $request->source,
                'forward_to_country_code'   => PhoneNumber::countryCode($request->forward_to_number),
                'forward_to_number'         => PhoneNumber::phone($request->forward_to_number),
                'audio_clip_id'             => $request->audio_clip,
                'recording_enabled_at'      => $request->record ? date('Y-m-d H:i:s') : null,
                'whisper_message'           => $request->whisper_messsage,
                'whisper_language'          => $request->whisper_language,
                'whisper_voice'             => $request->whisper_voice
            ]);
        }catch(Exception $e){
            throw $e;
            return response([
                'error' => 'Unable to complete purchase - please try another number'
            ], 400);
        }

        return response([
            'phone_number' => $phoneNumber
        ], 201);
    }

    /**
     * Read a phone number
     * 
     */
    public function read(Request $request, PhoneNumber $phoneNumber)
    {
        $user = $request->user();

        if( $phoneNumber->company_id != $user->company_id ){
            return response([
                'error' => 'Not found'
            ], 404);
        }

        return response([
            'phone_number' => $phoneNumber
        ]);
    }

    /**
     * Update a phone number
     * 
     */
    public function update(Request $request, PhoneNumber $phoneNumber)
    {
        $config = config('services.twilio');
        $user   = $request->user();

        if( $phoneNumber->company_id != $user->company_id ){
            return response([
                'error' => 'Not found'
            ], 404);
        }

        $rules = [
            'phone_number_pool' => ['bail', new PhoneNumberPoolRule($user->company_id)],
            'name'              => 'bail|required|max:255',
            'source'            => 'bail|required|max:255',
            'forward_to_number' => 'bail|required|digits_between:10,13',
            'audio_clip'        => ['bail', 'numeric', new AudioClipRule($user->company_id)],
            'record'            => 'boolean',
            'whisper_message'   => 'max:255',
            'whisper_language'  => 'in:' . implode(',', array_keys($config['languages'])),
            'whisper_voice'     => 'in:' . implode(',', array_keys($config['voices'])),
        ];

        $validator = Validator::make($request->input(), $rules);
        if( $validator->fails() ){
            return response([
                'error' => $validator->errors()->first()
            ], 400);
        }

        $phoneNumber->name                      = $request->name;
        $phoneNumber->source                    = $request->source;
        $phoneNumber->phone_number_pool_id      = $request->phone_number_pool;
        $phoneNumber->forward_to_country_code   = PhoneNumber::countryCode($request->forward_to_number) ?: null;
        $phoneNumber->forward_to_number         = PhoneNumber::phone($request->forward_to_number) ?: null;
        $phoneNumber->audio_clip_id             = $request->audio_clip;
        $phoneNumber->recording_enabled_at      = $request->record ? ($phoneNumber->recording_enabled_at ?: date('Y-m-d H:i:s')) : null;
        $phoneNumber->whisper_message           = $request->whisper_message;
        $phoneNumber->whisper_language          = $request->whisper_language;
        $phoneNumber->whisper_voice             = $request->whisper_voice;
        $phoneNumber->save();

        return response([
            'phone_number' => $phoneNumber
        ]);
    }

    /**
     * Delete a phone number
     * 
     */
    public function delete(Request $request, PhoneNumber $phoneNumber)
    {
        $user = $request->user();
        
        if( $phoneNumber->company_id != $user->company_id ){
            return response([
                'error' => 'Not found'
            ], 404);
        }

        if( $phoneNumber->isInUse() ){
            return response([
                'error' => 'This phone number is in use - please detach from all related entities and try again'
            ], 400);
        }

        $phoneNumber->release();

        return response([
            'message' => 'deleted'
        ]);
    }
}
