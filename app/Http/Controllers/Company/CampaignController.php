<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Models\Company;
use App\Models\Company\PhoneNumber;
use App\Models\Company\PhoneNumberPool;
use App\Models\Company\Campaign;
use App\Rules\Company\CampaignNumberSwapRule;
use \App\Rules\Company\PhoneNumberPoolRule;
use Validator;

class CampaignController extends Controller
{
    /**
     * List phone numbers
     * 
     * @param Illuminate\Http\Request $request      The incoming request
     * @param Illuminate\Http\Company $company      The associated company
     * 
     * @return Illuminate\Http\Response
     */
    public function list(Request $request, Company $company)
    {
        $limit  = intval($request->limit) ?: 25;
        $page   = intval($request->page) ? intval($request->page) - 1 : 0;
        $search = $request->search;

        $query = Campaign::where('company_id', $company->id);
        
        if( $search ){
            $query->where('name', 'like', '%' . $search . '%');
        }

        $resultCount = $query->count();
        $records     = $query->offset($page * $limit)
                             ->limit($limit)
                             ->get();

        return response([
            'message'       => 'success',
            'campaigns'     => $records,
            'result_count'  => $resultCount,
            'limit'         => $limit,
            'page'          => $page + 1,
            'total_pages'   => ceil($resultCount / $limit)
        ]);
    }

    /**
     * Create new resource
     * 
     * @param Illuminate\Http\Request $request      The incoming request
     * @param Illuminate\Http\Company $company      The associated company
     * 
     * @return Illuminate\Http\Response
     */
    public function create(Request $request, Company $company)
    {
        $rules = [
            'name'              => 'bail|required|max:255',
            'type'              => 'bail|required|in:' . implode(',', Campaign::types()),
            'active'            => 'required|bool'
        ]; 

        $validator = Validator::make($request->input(), $rules);

        $validator->sometimes('number_swap_rules', ['bail', 'required', 'json', new CampaignNumberSwapRule() ],function($input){
            return $input->type == Campaign::TYPE_WEB;
        });

        if( $validator->fails() ){
            return response([
                'error' =>  $validator->errors()->first()
            ], 400);
        }
        
        $campaign = Campaign::create([
            'uuid'              => Str::uuid(),
            'company_id'        => $company->id,
            'created_by'        => $request->user()->id,
            'name'              => $request->name,
            'type'              => $request->type,
            'number_swap_rules' => $request->number_swap_rules,
            'activated_at'      => $request->active ? date('Y-m-d H:i:s') : null  
        ]);

        return response([
            'campaign' => $campaign
        ], 201);
    }

    /**
     * Read new resource
     * 
     */
    public function read(Request $request, Company $company, Campaign $campaign)
    {   
        return response([
            'campaign' => $campaign
        ]);
    }

    /**
     * Update Resource
     * 
     */
    public function update(Request $request, Company $company, Campaign $campaign)
    {
        $rules = [
            'name'   => 'bail|required|max:255',
            'active' => 'required|bool'
        ]; 

        $validator = Validator::make($request->input(), $rules);

        $validator->sometimes('number_swap_rules', ['bail', 'required', 'json', new CampaignNumberSwapRule() ],function($input){
            return $input->type == Campaign::TYPE_WEB;
        });
    
        if( $validator->fails() ){
            return response([
                'error' =>  $validator->errors()->first()
            ], 400);
        }

        $campaign->name         = $request->name;
        $campaign->activated_at = boolval($request->active) 
                                ? ($campaign->activated_at ?: date('Y-m-d H:i:s')) 
                                : null;
        $campaign->save();

        return response([
            'campaign' => $campaign
        ], 200);
    }


    /**
     * Delete resource
     * 
     */
    public function delete(Request $request, Company $company, Campaign $campaign)
    {
        //  Do not allow users to delete active campaigns
        if( $campaign->active() ){
            return response([
                'error' => 'You cannot delete an active campaign'
            ], 400);
        }

        $campaign->delete();

        return response([
            'message' => 'deleted'
        ]);
    }
}
