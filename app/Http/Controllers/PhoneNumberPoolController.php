<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use \App\Rules\AudioClipRule;
use \App\Models\AudioClip;
use \App\Models\PhoneNumber;
use \App\Models\PhoneNumberPool;
use Validator;

class PhoneNumberPoolController extends Controller
{
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
        $query = PhoneNumberPool::where('company_id', $user->company_id);
        if( $search = $request->search ){
            $query->where(function($query) use($search){
                $query->where('name', 'like', $search . '%')
                      ->orWhere('source', 'like', $search . '%')
                      ->orWhere('forward_to_number', 'like', $search . '%');
            });
        }

        $totalCount = $query->count();
        
        $query->offset($request->start ?: 0);
        $query->limit($request->limit ?: 25);

        $pools = $query->get();

        return response([
            'phone_number_pools' => $pools,
            'result_count'       => count($pools),
            'total_count'        => $totalCount,
            'message'            => 'success'
        ]);
    }

    public function create(Request $request)
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

    public function read(Request $request, PhoneNumberPool $phoneNumberPool)
    {
        $user = $request->user();
        if( $phoneNumberPool->company_id != $user->company_id ){
            return response([
                'error' => 'Not found'
            ], 404);
        }

        return response([
            'phone_number_pool' => $phoneNumberPool,
            'message' => 'success'
        ]);
    }

    public function update(Request $request, PhoneNumberPool $phoneNumberPool)
    {
        $config = config('services.twilio');
        $user   = $request->user();
        
        if( ! $phoneNumberPool || $phoneNumberPool->company_id != $user->company_id ){
            return response([
                'error' => 'Not found'
            ], 404);
        }

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

    public function delete(Request $request, PhoneNumberPool $phoneNumberPool)
    {
        $user = $request->user();
        if( ! $phoneNumberPool || $phoneNumberPool->company_id != $user->company_id ){
            return response([
                'error' => 'Not found'
            ], 404);
        }

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
        ], 200);
    }


}
