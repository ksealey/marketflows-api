<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use \App\Rules\Company\AudioClipRule;
use \App\Models\Company;
use \App\Models\Company\AudioClip;
use \App\Models\Company\PhoneNumberPool;
use \App\Models\Company\PhoneNumber;
use Validator;

class PhoneNumberPoolController extends Controller
{
    /**
     * List phone number pools
     * 
     * @param Request $company
     * @param Company $company
     * 
     * @return Response
     */
    public function list(Request $request, Company $company)
    {
        $limit  = intval($request->limit) ?: 25;
        $page   = intval($request->page) ? intval($request->page) - 1 : 0;
        $search = $request->search;
        
        $user  = $request->user();
        $query = PhoneNumberPool::where('company_id', $company->id);
        
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
            'message'               => 'success',
            'phone_number_pools'    => $records,
            'result_count'          => $resultCount,
            'limit'                 => $limit,
            'page'                  => $page + 1,
            'total_pages'           => ceil($resultCount / $limit)
        ]);
    }

    /**
     * Create a phone number pool
     * 
     * @param Request $request
     * @param Company $company
     * 
     * @return Response
     */
    public function create(Request $request, Company $company)
    {
        $config = config('services.twilio');
        $user   = $request->user();

        $rules = [
            'name'                      => 'bail|required|max:255',
            'source'                    => 'bail|required|max:255',
            'forward_to_country_code'   => 'bail|digits_between:1,4',
            'forward_to_number'         => 'bail|required|digits:10',
            'audio_clip'                => ['bail', 'numeric', new AudioClipRule($user->company_id)],
            'record'                    => 'boolean',
            'whisper_message'           => 'max:255',
            'whisper_language'          => 'in:' . implode(',', array_keys($config['languages'])),
            'whisper_voice'             => 'in:' . implode(',', array_keys($config['voices'])),      
        ];

        $validator = Validator::make($request->input(), $rules);
        if( $validator->fails() ){
            return response([
                'error' =>  $validator->errors()->first()
            ], 400);
        }

        $phoneNumberPool = PhoneNumberPool::create([
            'company_id'                => $user->company_id,
            'created_by'                => $user->id,
            'name'                      => $request->name, 
            'source'                    => $request->source, 
            'forward_to_country_code'   => $request->forward_to_country_code,
            'forward_to_number'         => $request->forward_to_number,
            'audio_clip_id'             => $request->audio_clip,
            'recording_enabled_at'      => $request->record ? date('Y-m-d H:i:s') : null,
            'whisper_message'           => $request->whisper_message,
            'whisper_language'          => $request->whisper_language,
            'whisper_voice'             => $request->whisper_voice
        ]);

        return response([
            'phone_number_pool' => $phoneNumberPool,
            'message'           => 'created'
        ], 201);
    }

    /**
     * View a phone number pool
     * 
     * @param Request $company
     * @param Company $company
     * @param PhoneNumberPool $phoneNumberPool
     * 
     * @return Response
     */
    public function read(Request $request, Company $company, PhoneNumberPool $phoneNumberPool)
    {
        return response([
            'phone_number_pool' => $phoneNumberPool,
            'message'           => 'success'
        ]);
    }

    /**
     * Update a phone number pool
     * 
     * @param Request $company
     * @param Company $company
     * @param PhoneNumberPool $phoneNumberPool
     * 
     * @return Response
     */
    public function update(Request $request, Company $company, PhoneNumberPool $phoneNumberPool)
    {
        $config = config('services.twilio');
        $user   = $request->user();
        $rules = [
            'name'                      => 'bail|required|max:255',
            'source'                    => 'bail|required|max:255',
            'forward_to_country_code'   => 'bail|digits_between:1,4',
            'forward_to_number'         => 'bail|required|digits:10',
            'audio_clip'                => ['bail', 'numeric', new AudioClipRule($phoneNumberPool->company_id)],
            'record'                    => 'boolean',
            'whisper_message'           => 'max:255',
            'whisper_language'          => 'in:' . implode(',', array_keys($config['languages'])),
            'whisper_voice'             => 'in:' . implode(',', array_keys($config['voices'])), 
        ];

        $validator = Validator::make($request->input(), $rules);
        if( $validator->fails() ){
            return response([
                'error' =>  $validator->errors()->first()
            ], 400);
        }

        if( $request->audio_clip ){
            $audioClip = AudioClip::find($request->audio_clip);
            if( ! $audioClip || $audioClip->company_id != $user->company_id ){
                return response([
                    'error' => 'Audio clip not found'
                ], 400);
            }
        }

        $phoneNumberPool->name                      = $request->name;
        $phoneNumberPool->source                    = $request->source;
        $phoneNumberPool->forward_to_country_code   = $request->forward_to_country_code;
        $phoneNumberPool->forward_to_number         = $request->forward_to_number;
        $phoneNumberPool->recording_enabled_at      = $request->record ? ($phoneNumberPool->recording_enabled_at ?: date('Y-m-d H:i:s')) : null;
        $phoneNumberPool->whisper_message           = $request->whisper_message;
        $phoneNumberPool->whisper_language          = $request->whisper_language;
        $phoneNumberPool->whisper_voice             = $request->whisper_voice;
        $phoneNumberPool->save();

        return response([
            'phone_number_pool' => $phoneNumberPool,
            'message'           => 'updated'
        ], 200);
    }

    /**
     * Delete a phone number pool
     * 
     * @param Request $company
     * @param Company $company
     * @param PhoneNumberPool $phoneNumberPool
     * 
     * @return Response
     */
    public function delete(Request $request, Company $company, PhoneNumberPool $phoneNumberPool)
    {
        if( $phoneNumberPool->isInUse() ){
            return response([
                'error' => 'This phone number pool is in use - please detach from all related entities and try again'
            ], 400);
        }

        //  Detach phone numbers from pool
        PhoneNumber::where('phone_number_pool_id', $phoneNumberPool->id)
                   ->update(['phone_number_pool_id' => null]);

        $phoneNumberPool->delete();

        return response([
            'message' => 'deleted'
        ]);
    }
}
