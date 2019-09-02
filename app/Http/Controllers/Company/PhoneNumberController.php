<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Rules\Company\PhoneNumberPoolRule;
use App\Rules\Company\AudioClipRule;
use App\Models\Company;
use App\Models\Company\AudioClip;
use App\Models\Company\PhoneNumber;
use Validator;
use Exception;

class PhoneNumberController extends Controller
{
    /**
     * List phone numbers
     * 
     */
    public function list(Request $request, Company $company)
    {
        $limit  = intval($request->limit) ?: 25;
        $page   = intval($request->page) ? intval($request->page) - 1 : 0;
        $search = $request->search;

        $query = PhoneNumber::where('company_id', $company->id);
        
        if( $search ){
            $query->where(function($query) use($search){
                $query->where('name', 'like', '%' . $search . '%')
                      ->orWhere('source', 'like', '%' . $search . '%')
                      ->orWhere('forward_to_number', 'like', '%' . $search . '%');
            });
        }

        $resultCount = $query->count();
        $records     = $query->offset($page * $limit)
                             ->limit($limit)
                             ->get();

        return response([
            'message'       => 'success',
            'phone_numbers' => $records,
            'result_count'  => $resultCount,
            'limit'         => $limit,
            'page'          => $page + 1,
            'total_pages'   => ceil($resultCount / $limit)
        ]);
    }

    /**
     * Create a phone number
     * 
     */
    public function create(Request $request, Company $company)
    {
        $config = config('services.twilio');
        $rules = [
            'phone_number_pool' => ['bail', new PhoneNumberPoolRule($company)],
            'number'            => 'bail|required|digits_between:10,13',
            'name'              => 'bail|required|max:255',
            'source'            => 'bail|required|max:255',
            'forward_to_number' => 'bail|required|digits_between:10,13',
            'audio_clip'        => ['bail', 'numeric', new AudioClipRule($company->id)],
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

        $user  = $request->user();

        //  Purchase a phone number
        try{
            $numData = PhoneNumber::purchase($request->number);
            $can     = $numData['capabilities'];

            $phoneNumber = PhoneNumber::create([
                'company_id'                => $company->id,
                'created_by'                => $user->id,
                'external_id'               => $numData['sid'],
                'country_code'              => $numData['country_code'],
                'number'                    => $numData['number'],
                'voice'                     => $can['voice'],
                'sms'                       => $can['sms'],
                'mms'                       => $can['mms'],
                'phone_number_pool_id'      => $request->phone_number_pool,
                'name'                      => $request->name,
                'source'                    => $request->source,
                'forward_to_country_code'   => PhoneNumber::countryCode($request->forward_to_number),
                'forward_to_number'         => PhoneNumber::number($request->forward_to_number),
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
    public function read(Request $request, Company $company, PhoneNumber $phoneNumber)
    {
        return response([
            'phone_number' => $phoneNumber
        ]);
    }

    /**
     * Update a phone number
     * 
     */
    public function update(Request $request, Company $company, PhoneNumber $phoneNumber)
    {
        $config = config('services.twilio');
        
        $rules = [
            'phone_number_pool' => ['bail', new PhoneNumberPoolRule($company)],
            'name'              => 'bail|required|max:255',
            'source'            => 'bail|required|max:255',
            'forward_to_number' => 'bail|required|digits_between:10,13',
            'audio_clip'        => ['bail', 'numeric', new AudioClipRule($company->id)],
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
        $phoneNumber->forward_to_number         = PhoneNumber::number($request->forward_to_number) ?: null;
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
    public function delete(Request $request, Company $company, PhoneNumber $phoneNumber)
    {
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
