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
        
        $query = PhoneNumberPool::where('company_id', $company->id);
        
        if( $search ){
            $query->where(function($query) use($search){
                $query->where('name', 'like', '%' . $search . '%');
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
        $rules = [
            'name'                      => 'bail|required|max:255',
            'auto_provision'            => 'boolean',
        ];

        $validator = Validator::make($request->input(), $rules);
        if( $validator->fails() ){
            return response([
                'error' =>  $validator->errors()->first()
            ], 400);
        }

        $user = $request->user();
        
        $phoneNumberPool = PhoneNumberPool::create([
            'company_id'                => $company->id,
            'created_by'                => $user->id,
            'name'                      => $request->name, 
            'auto_provision_enabled_at' => $request->auto_provision ? date('Y-m-d H:i:s') : null
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
        $rules = [
            'name'                      => 'bail|required|max:255',
            'source'                    => 'bail|required|max:255',
        ];

        $validator = Validator::make($request->input(), $rules);
        if( $validator->fails() ){
            return response([
                'error' =>  $validator->errors()->first()
            ], 400);
        }

        $phoneNumberPool->name   = $request->name;
        $phoneNumberPool->source = $request->source;
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
                'error' => 'This phone number pool is in use'
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
