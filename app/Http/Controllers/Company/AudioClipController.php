<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Company;
use App\Models\Company\AudioClip;
use App\Events\Company\AudioClipEvent;
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
        $fields = [
            'audio_clips.id',
            'audio_clips.name',
            'audio_clips.created_at',
            'audio_clips.updated_at'
        ];

        $query = AudioClip::where('company_id', $company->id);
        
        return parent::results(
            $request,
            $query,
            [],
            AudioClip::accessibleFields(),
            'audio_clips.created_at'
        );
    }

    /**
     * Upload an audio clip
     * 
     */
    public function create(Request $request, Company $company)
    {
        $rules = [
            'audio_clip'  => 'required|file',
            'name'        => 'required|max:64'
        ];

        $validator = validator($request->all(), $rules);
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
            $filePath = Storage::putFile(AudioClip::storagePath($company->account_id, $company->id, 'audio_clips'), $file);

            //  Log in database
            $audioClip = AudioClip::create([
                'account_id'    => $company->account_id,
                'company_id'    => $company->id,
                'name'          => $request->name,
                'path'          => $filePath,
                'mime_type'     => $file->getMimeType(),
                'created_by'    => $user->id
            ]);
        }catch(Exception $e){
            DB::rollBack();

            throw $e;
        }

        DB::commit();

        return response($audioClip, 201);
    }

    /**
     * View an audio clip
     * 
     */
    public function read(Request $request, Company $company, AudioClip $audioClip)
    {
        return response($audioClip);
    }

    /**
     * Update an audio clip
     * 
     */
    public function update(Request $request, Company $company, AudioClip $audioClip)
    {
        $validator = validator($request->all(), [
            'audio_clip'  => 'file|mimes:x-flac,mpeg,x-wav',
            'name'        => 'min:1|max:64'
        ]);

        if( $validator->fails() )
            return response([
                'error' => $validator->errors()->first(),
            ], 400);

        if( $request->hasFile('audio_clip') )
            Storage::put($audioClip->path, $request->audio_clip);

        if( $request->filled('name') )
            $audioClip->name = $request->name;

        $audioClip->updated_by = $request->user()->id;
        $audioClip->save();
    
        return response($audioClip);
    }

    public function delete(Request $request, Company $company, AudioClip $audioClip)
    {
        if( $audioClip->isInUse() ){
            return response([
                'error' => 'This audio clip is in use - please remove from all number configurations and try again.',
            ], 400);
        }
        
        $audioClip->deleted_by = $request->user()->id;
        $audioClip->save();
        $audioClip->deleteRemoteResource();
        $audioClip->delete();

        return response([
            'message' => 'Deleted',
        ], 200);
    }
}
