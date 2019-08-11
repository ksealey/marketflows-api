<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\AudioClip;
use Storage;
use DB;
use Validator;
use Exception;

class AudioClipController extends Controller
{
    /**
     * List resources
     * 
     */
    public function list(Request $request)
    {
        $user  = $request->user();

        $start = intval($request->input('start', 0));
        $limit = intval($request->input('limit', 0)) ?: 25;

        $query      = AudioClip::where('company_id', $user->company_id); 
        $totalCount = $query->count();

        if( $request->search )
            $query->where('name', 'like', '%' . $request->search . '%');

        $audioClips = $query->offset($start)
                            ->limit($limit)
                            ->get();
        
        return response([
            'message'         => 'success',
            'ok'              => true,
            'audio_clips'     => $audioClips,
            'result_count'    => count($audioClips),
            'total_count'     => $totalCount
        ]);
    }

    /**
     * Upload an audio clip
     * 
     */
    public function create(Request $request)
    {
        $rules = [
            'audio_clip'  => 'required|file|mimes:x-flac,mpeg,x-wav',
            'name'        => 'required|max:255'
        ];

        $validator = Validator::make($request->all(), $rules);
        if( $validator->fails() ){
            return response([
                'error' => $validator->errors()->first(),
                'ok'    => false
            ], 404);
        }

        $user = $request->user();

        DB::beginTransaction();
        try{
            $file =  $request->audio_clip; 

            //  Upload file
            $filePath = Storage::putFile($user->company_id . '/audio_clips', $file);

            //  Log in database
            $audioClip = AudioClip::create([
                'company_id'  => $user->company_id,
                'created_by' => $user->id,
                'name'        => $request->name,
                'path'        => $filePath,
                'mime_type'   => $file->getMimeType()
            ]);
        }catch(Exception $e){

            DB::rollBack();

            return response([
                'error' => 'Unable to upload file',
                'ok'    => false
            ], 400);
        }

        return response([
            'message'       => 'created',
            'ok'            => true,
            'audio_clip'    => $audioClip
        ], 201);
    }

    /**
     * View an audio clip
     * 
     */
    public function read(Request $request, AudioClip $audioClip)
    {
        $user = $request->user();
        if( ! $audioClip || $user->company_id != $audioClip->company_id ){
            return response([
                'error' => 'Not found',
                'ok'    => false
            ], 400);
        }

        return response([
            'message'       => 'success',
            'ok'            => true,
            'audio_clip'    => $audioClip
        ], 200);
    }

    /**
     * Update an audio clip
     * 
     */
    public function update(Request $request,  AudioClip $audioClip)
    {
        $user = $request->user();
        if( ! $audioClip || $user->company_id != $audioClip->company_id ){
            return response([
                'error' => 'Not found',
                'ok'    => false
            ], 400);
        }

        $rules = [
            'audio_clip'  => 'file|mimes:x-flac,mpeg,x-wav',
            'name'        => 'max:255'
        ];

        $validator = Validator::make($request->all(), $rules);
        if( $validator->fails() ){
            return response([
                'error' => $validator->errors()->first(),
                'ok'    => false
            ], 404);
        }

        DB::beginTransaction();
        try{
            //  Replace existing file if provided
            if( $file = $request->audio_clip )
                Storage::put($audioClip->path, $file);

            if( $request->name )
                $audioClip->name = $request->name;

            if( $file || $request->name )
                $audioClip->save();
        
        }catch(Exception $e){

            DB::rollBack();

            return response([
                'error' => 'Unable to upload file',
                'ok'    => false
            ], 400);
        }

        return response([
            'message'       => 'updated',
            'ok'            => true,
            'audio_clip'    => $audioClip
        ], 200);
    }

    public function delete(Request $request,  AudioClip $audioClip)
    {
        $user = $request->user();
        if( ! $audioClip || $user->company_id != $audioClip->company_id ){
            return response([
                'error' => 'Not found',
                'ok'    => false
            ], 400);
        }

        if( ! $audioClip->canBeDeleted() ){
            return response([
                'error' => 'This audio clip cannot be deleted - first, please remove from all active campaigns and try again',
                'ok'    => false
            ], 400);
        }

        Storage::delete($audioClip->path);
        
        $audioClip->delete();

        return response([
            'message'       => 'deleted',
            'ok'            => true
        ], 200);

    }
}
