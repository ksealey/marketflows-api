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
use DateTime;
use DateTimeZone;
use App\Helpers\Formatter;

class PhoneNumberConfigController extends Controller
{
    /**
     * List phone number configs
     * 
     */
    public function list(Request $request, Company $company)
    {
        $rules = [
            'limit'     => 'numeric',
            'page'      => 'numeric',
            'order_by'  => 'in:name,created_at,updated_at',
            'order_dir' => 'in:asc,desc'  
        ];

        $validator = Validator::make($request->input(), $rules);
        if( $validator->fails() ){
            return response([
                'error' => $validator->errors()->first()
            ], 400);
        }

        $limit      = intval($request->limit) ?: 250;
        $limit      = $limit > 250 ? 250 : $limit;
        $page       = intval($request->page)  ?: 1;
        $orderBy    = $request->order_by  ?: 'created_at';
        $orderDir   = strtoupper($request->order_dir) ?: 'DESC';
        $search     = $request->search;
        
        $query = PhoneNumberConfig::where('company_id', $company->id);
        
        if( $search ){
            $query->where(function($query) use($search){
                $query->where('name', 'like', '%' . $search . '%')
                      ->orWhere('forward_to_number', 'like', '%' . $search . '%');
            });
        }

        $resultCount = $query->count();
        $records     = $query->offset(( $page - 1 ) * $limit)
                             ->limit($limit)
                             ->orderBy($orderBy, $orderDir)
                             ->get();

        //  Set local time
        $now             = new DateTime(); 
        $defaultTimezone = new DateTimeZone('UTC');
        $companyTimezone = new DateTimeZone($company->timezone);

        foreach( $records as $record ){
            $createdAt = new DateTime($record->created_at, $defaultTimezone);
            $updatedAt = new DateTime($record->updated_at, $defaultTimezone);

            $record->offset_times = [
                'created_at' => Formatter::offsetTimeString($now->diff($createdAt)),
                'updated_at' => Formatter::offsetTimeString($now->diff($updatedAt)),
            ];

            $createdAt->setTimeZone($companyTimezone);
            $updatedAt->setTimeZone($companyTimezone);
            
            $record->local_times = [
                'created_at'          => $createdAt->format('Y-m-d H:i:s'),
                'created_at_friendly' => $createdAt->format('F jS, Y') . ' at ' . $createdAt->format('g:ia'),
                'updated_at'          => $updatedAt->format('Y-m-d H:i:s'),
                'updated_at_friendly' => $updatedAt->format('F jS, Y') . ' at ' . $updatedAt->format('g:ia')
            ];

            
        }

        $nextPage = null;
        if( $resultCount > ($page * $limit) )
            $nextPage = $page + 1;

        return response([
            'results'              => $records,
            'result_count'         => $resultCount,
            'limit'                => $limit,
            'page'                 => $page,
            'total_pages'          => ceil($resultCount / $limit),
            'next_page'            => $nextPage
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
        //
        //  Attach phone numbers and phone number pools
        //
        
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
                'error' => 'This configuration is in use - Detach from all phone numbers and phone number pools then try again'
            ], 400);
        }

        $phoneNumberConfig->delete();

        return response([
            'message' => 'deleted'
        ]);
    }
}
