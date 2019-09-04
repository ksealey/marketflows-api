<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Company;
use App\Models\Company\AudioClip;
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
    public function list(Request $request, Company $company)
    {
        $limit  = intval($request->limit) ?: 25;
        $page   = intval($request->page) ? intval($request->page) - 1 : 0;
        $search = $request->search;
        
        $query  = AudioClip::where('company_id', $company->id);
        
        if( $search )
            $query->where('name', 'like', '%' . $search . '%');

        $resultCount = $query->count();
        $records     = $query->offset($page * $limit)
                             ->limit($limit)
                             ->get();

        return response([
            'message'         => 'success',
            'audio_clips'     => $records,
            'result_count'    => $resultCount,
            'limit'           => $limit,
            'page'            => $page + 1,
            'total_pages'     => ceil($resultCount / $limit)
        ]);
    }

    /**
     * Upload an audio clip
     * 
     */
    public function create(Request $request, Company $company)
    {
        $rules = [
            'audio_clip'  => 'required|file|mimes:x-flac,mpeg,x-wav',
            'name'        => 'required|max:255'
        ];

        $validator = Validator::make($request->all(), $rules);
        if( $validator->fails() ){
            return response([
                'error' => $validator->errors()->first(),
            ], 400);
        }

        $user = $request->user();

        DB::beginTransaction();
        try{
            $file =  $request->audio_clip; 

            //  Upload file
            $filePath = Storage::putFile(AudioClip::storagePath($company, 'audio_clips'), $file);

            //  Log in database
            $audioClip = AudioClip::create([
                'company_id'  => $company->id,
                'created_by'  => $user->id,
                'name'        => $request->name,
                'path'        => $filePath,
                'mime_type'   => $file->getMimeType()
            ]);

            $this->logUserEvent($user, 'audio-clips.create', $audioClip);
        }catch(Exception $e){
            DB::rollBack();

            return response([
                'error' => 'Unable to upload file',
            ], 400);
        }

        DB::commit();

        return response([
            'message'       => 'created',
            'audio_clip'    => $audioClip
        ], 201);
    }

    /**
     * View an audio clip
     * 
     */
    public function read(Request $request, Company $company, AudioClip $audioClip)
    {
        return response([
            'message'       => 'success',
            'audio_clip'    => $audioClip
        ], 200);
    }

    /**
     * Update an audio clip
     * 
     */
    public function update(Request $request, Company $company, AudioClip $audioClip)
    {
        $rules = [
            'audio_clip'  => 'file|mimes:x-flac,mpeg,x-wav',
            'name'        => 'required|max:255'
        ];

        $validator = Validator::make($request->all(), $rules);
        if( $validator->fails() ){
            return response([
                'error' => $validator->errors()->first(),
                'ok'    => false
            ], 400);
        }

        DB::beginTransaction();
        try{
            //  Replace existing file if provided
            if( $file = $request->audio_clip )
                Storage::put($audioClip->path, $file);

            $old = clone $audioClip;

            $audioClip->name = $request->name;
            $audioClip->save();

            $this->logUserEvent($request->user(), 'audio-clips.update', $old, $audioClip);
        }catch(Exception $e){
            DB::rollBack();

            return response([
                'error' => 'Unable to upload file',
            ], 400);
        }
        DB::commit();

        return response([
            'message'    => 'updated',
            'audio_clip' => $audioClip
        ], 200);
    }

    public function delete(Request $request, Company $company, AudioClip $audioClip)
    {
        if( ! $audioClip->canBeDeleted() ){
            return response([
                'error' => 'This audio clip cannot be deleted - please remove from all active phone numbers and phone number pools and try again',
            ], 400);
        }

        Storage::delete($audioClip->path);
        
        $audioClip->delete();

        $this->logUserEvent($request->user(), 'audio-clips.delete', $audioClip);

        return response([
            'message' => 'deleted',
        ], 200);
    }
}
