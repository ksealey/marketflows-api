<?php

namespace App\Http\Controllers\Company;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Rules\Company\PhoneNumberPoolRule;
use App\Rules\Company\AudioClipRule;
use App\Models\Company;
use App\Models\Company\PhoneNumber;
use App\Models\Company\PhoneNumberConfig;
use Validator;

class PhoneNumberConfigController extends Controller
{
    /**
     * List phone number configs
     * 
     */
    public function list(Request $request, Company $company)
    {
        $limit  = intval($request->limit) ?: 25;
        $limit  = $limit > 1000 ? 1000 : $limit;
        $page   = intval($request->page) ? intval($request->page) - 1 : 0;
        $search = $request->search;

        $query = PhoneNumberConfig::where('company_id', $company->id);
        
        if( $search ){
            $query->where(function($query) use($search){
                $query->where('name', 'like', '%' . $search . '%')
                      ->orWhere('forward_to_number', 'like', '%' . $search . '%');
            });
        }

        $query->orderBy('created_at', 'DESC');

        $resultCount = $query->count();
        $records     = $query->offset($page * $limit)
                             ->limit($limit)
                             ->get();

        return response([
            'results'              => $records,
            'result_count'         => $resultCount,
            'limit'                => $limit,
            'page'                 => $page + 1,
            'total_pages'          => ceil($resultCount / $limit)
        ]);
    }

    /**
     * Create a phone number config
     * 
     */
    public function create(Request $request, Company $company)
    {
       $rules = [
            'name'              => 'bail|required|max:255',
            'forward_to_number' => 'bail|required|digits_between:10,13',
            'audio_clip'        => ['bail', 'numeric', new AudioClipRule($company->id)],
            'record'            => 'bail|boolean',
            'whisper_message'   => 'bail|max:255',
        ];

        $validator = Validator::make($request->input(), $rules);
        if( $validator->fails() ){
            return response([
                'error' => $validator->errors()->first()
            ], 400);
        }

        $phoneNumberConfig = PhoneNumberConfig::create([
            'company_id'                => $company->id,
            'created_by'                => $request->user()->id,
            'name'                      => $request->name,
            'forward_to_country_code'   => PhoneNumber::countryCode($request->forward_to_number),
            'forward_to_number'         => PhoneNumber::number($request->forward_to_number),
            'audio_clip_id'             => $request->audio_clip ?: null,
            'recording_enabled_at'      => $request->record ? date('Y-m-d H:i:s') : null,
            'whisper_message'           => $request->whisper_message ?: null
        ]);

        return response($phoneNumberConfig, 201)
                ->withHeaders([
                    'Location' => $phoneNumberConfig->link
                ]);
    }

    /**
     * Read a phone number config
     * 
     */
    public function read(Request $request, Company $company, PhoneNumberConfig $phoneNumberConfig)
    {
        return response( $phoneNumberConfig );
    }

    /**
     * Update a phone number config
     * 
     */
    public function update(Request $request, Company $company, PhoneNumberConfig $phoneNumberConfig)
    {
        $rules = [
            'name'              => 'bail|required|max:255',
            'forward_to_number' => 'bail|required|digits_between:10,13',
            'audio_clip'        => ['bail', 'numeric', new AudioClipRule($company->id)],
            'record'            => 'bail|boolean',
            'whisper_message'   => 'bail|max:255',
        ];

        $validator = Validator::make($request->input(), $rules);
        if( $validator->fails() ){
            return response([
                'error' => $validator->errors()->first()
            ], 400);
        }

        $phoneNumberConfig->name                    = $request->name;
        $phoneNumberConfig->forward_to_country_code = PhoneNumber::countryCode($request->forward_to_number);
        $phoneNumberConfig->forward_to_number       = PhoneNumber::number($request->forward_to_number);
        $phoneNumberConfig->audio_clip_id           = $request->audio_clip;
        $phoneNumberConfig->recording_enabled_at    = $request->record ? ( $phoneNumberConfig->recording_enabled_at ?: date('Y-m-d H:i:s') ) : null;
        $phoneNumberConfig->whisper_message         = $request->whisper_message;
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
                'error' => 'Phone number config in use'
            ], 400);
        }

        $phoneNumberConfig->delete();

        return response([
            'message' => 'deleted'
        ]);
    }
}
