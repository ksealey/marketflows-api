<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use \App\Models\Company;
use \App\Models\Company\Campaign;
use \App\Models\User;
use \App\Models\UserCompany;
use \App\Rules\CompanyWebhookActionsRule;
use Validator;

class CompanyController extends Controller
{
    /**
     * List all companies
     * 
     * @param Request $request
     * 
     * @return Response
     */
    public function list(Request $request)
    {
        $limit  = intval($request->limit) ?: 25;
        $page   = intval($request->page) ? intval($request->page) - 1 : 0;
        $search = $request->search;
        
        $user  = $request->user();
        $query = Company::where('account_id', $user->account_id);
        
        if( $search )
            $query->where('name', 'like', '%' . $search . '%');

        $resultCount = $query->count();
        $records     = $query->offset($page * $limit)
                             ->limit($limit)
                             ->orderBy('name', 'asc')
                             ->get();

        return response([
            'message'         => 'success',
            'companies'       => $records,
            'result_count'    => $resultCount,
            'limit'           => $limit,
            'page'            => intval($request->page),
            'total_pages'     => ceil($resultCount / $limit)
        ]);
    }

    /**
     * Create a company
     * 
     * @param Request $request
     * 
     * @return Response
     */
    public function create(Request $request)
    {
        $rules = [
            'name' => 'required|max:255',
        ];

        $validator = Validator::make($request->input(), $rules);
        if( $validator->fails() ){
            return response([
                'error' => $validator->errors()->first()
            ], 400);
        }

        $user = $request->user();

        $company = Company::create([
            'account_id'        => $user->account_id,
            'created_by'        => $user->id,
            'name'              => $request->name
        ]);

        return response([
            'message' => 'created',
            'company' => $company
        ], 201);
    }

    /**
     * View a company
     * 
     * @param Request $request
     * @param Company $company
     * 
     * @return Response
     */
    public function read(Request $request, Company $company)
    {
        $company->plugins = $company->plugins();
        
        return response([
            'message' => 'success',
            'company' => $company
        ]);
    }

    /**
     * Update a company
     * 
     * @param Request $request
     * @param Company $company
     * 
     * @return Response
     */
    public function update(Request $request, Company $company)
    {
        $rules = [
            'name' => 'required|max:255'
        ];
        
        $validator = Validator::make($request->input(), $rules);
        if( $validator->fails() ){
            return response([
                'error' => $validator->errors()->first()
            ], 400);
        }

        $company->name = $request->name;
        $company->save();

        return response([
            'message' => 'success',
            'company' => $company
        ]);
    }

    /**
     * Delete a company
     * 
     * @param Request $request
     * @param Company $company 
     * 
     * @return Response
     */
    public function delete(Request $request, Company $company)
    {
        if( $company->isInUse() ){
            return response([
                'error' => 'Company has active campaigns attached'
            ], 400);
        }        

        //  Remove any inactive campaigns attached
        Campaign::where('company_id', $company->id)
                ->delete();

        //  Remove any users attached
        UserCompany::where('company_id', $company->id)
                   ->delete();

        $company->delete();

        return response([
            'message' => 'deleted'
        ]);
    }
}
