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
        $page       = intval($request->page) ?: 1;
        $orderBy    = $request->order_by  ?: 'created_at';
        $orderDir   = strtoupper($request->order_dir) ?: 'DESC';
        $search     = $request->search;
        
        $query  = AudioClip::where('company_id', $company->id);
        
        if( $search )
            $query->where('name', 'like', '%' . $search . '%');

        $resultCount = $query->count();
        $records     = $query->limit($limit)
                             ->offset(($page - 1) * $limit)
                             ->orderBy($orderBy, $orderDir)
                             ->get();

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
     * Upload an audio clip
     * 
     */
    public function create(Request $request, Company $company)
    {
        $rules = [
            'audio_clip'  => 'required|file',
            'name'        => 'required|max:64'
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
                'company_id'    => $company->id,
                'user_id'       => $user->id,
                'name'          => $request->name,
                'path'          => $filePath,
                'mime_type'     => $file->getMimeType()
            ]);
        }catch(Exception $e){
            DB::rollBack();

            return response([
                'error' => 'Unable to upload file',
            ], 400);
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
            'name'        => 'required|max:64'
        ];

        $validator = Validator::make($request->all(), $rules);
        if( $validator->fails() ){
            return response([
                'error' => $validator->errors()->first(),
            ], 400);
        }

        DB::beginTransaction();
        try{
            if( $request->hasFile('audio_clip') )
                //  Replace existing file if provided
                Storage::put($audioClip->path, $request->audio_clip);

            if( $request->filled('name') )
                $audioClip->name = $request->name;

            $audioClip->save();
        }catch(Exception $e){
            DB::rollBack();

            return response([
                'error' => 'Unable to upload file',
            ], 400);
        }
        DB::commit();

        return response($audioClip, 200);
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

        return response([
            'message' => 'deleted',
        ], 200);
    }
}
