<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
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
        $rules = [
            'name'                      => 'required|max:255',
            'source'                    => 'required|max:255',
            'forward_to_country_code'   => 'digits_between:1,4',
            'forward_to_number'         => 'required|digits:10',
        ];

        $validator = Validator::make($request->input(), $rules);
        if( $validator->fails() ){
            return response([
                'error' =>  $validator->errors()->first()
            ], 400);
        }

        $user = $request->user();

        if( $request->audio_clip ){
            $audioClip = AudioClip::find($request->audio_clip);
            if( ! $audioClip || $audioClip->company_id != $user->company_id ){
                return response([
                    'error' => 'Audio clip not found'
                ], 400);
            }
        }

        $phoneNumberPool = PhoneNumberPool::create([
            'company_id'                => $user->company_id,
            'created_by'                => $user->id,
            'name'                      => $request->name, 
            'source'                    => $request->source, 
            'forward_to_country_code'   => $request->forward_to_country_code,
            'forward_to_number'         => $request->forward_to_number,
            'audio_clip_id'             => $request->audio_clip
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
        $user = $request->user();
        if( ! $phoneNumberPool || $phoneNumberPool->company_id != $user->company_id ){
            return response([
                'error' => 'Not found'
            ], 404);
        }

        $rules = [
            'name'                      => 'required|max:255',
            'source'                    => 'required|max:255',
            'forward_to_country_code'   => 'digits_between:1,4',
            'forward_to_number'         => 'required|digits:10',
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

        $phoneNumberPool->delete();

        return response([
            'message' => 'deleted'
        ], 200);
    }


}
