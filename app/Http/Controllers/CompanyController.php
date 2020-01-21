<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use \App\Models\Company;
use \App\Models\Company\Campaign;
use \App\Models\User;
use \App\Models\UserCompany;
use \App\Rules\CountryRule;
use \App\Rules\CompanyWebhookActionsRule;
use Validator;
use Exception;
use DB;

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
        $rules = [
            'limit'     => 'numeric',
            'page'      => 'numeric',
            'order_by'  => 'in:name',
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
        $page       = intval($request->page)  ?: 1;
        $orderBy    = $request->order_by  ?: 'id';
        $orderDir   = strtoupper($request->order_dir) ?: 'ASC';
        $search     = $request->search;
        
        $user  = $request->user();

        $query = Company::where('account_id', $user->account_id)
                        ->whereIn('id', function($query) use($user){
                            $query->select('company_id')
                                  ->from('user_companies')
                                  ->where('user_id', $user->id);
                        });
        
        if( $search )
            $query->where('name', 'like', '%' . $search . '%');

        $resultCount = $query->count();
        $records     = $query->offset(($page - 1) * $limit)
                             ->limit($limit)
                             ->orderBy('name', 'asc')
                             ->get();

        $nextPage = null;
        if( $resultCount > ($page * $limit) )
            $nextPage = $page + 1;

        return response([
            'results'         => $records,
            'result_count'    => $resultCount,
            'limit'           => $limit,
            'page'            => intval($request->page),
            'total_pages'     => ceil($resultCount / $limit),
            'next_page'       => $nextPage
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
            'name'      => 'bail|required|max:255',
            'industry'  => 'bail|required|max:255',
            'country'   => ['bail', 'required', new CountryRule()],
            'timezone'  => 'bail|required|timezone'
        ];

        $validator = Validator::make($request->input(), $rules);
        if( $validator->fails() ){
            return response([
                'error' => $validator->errors()->first()
            ], 400);
        }

        $user = $request->user();

        DB::beginTransaction();

        try{
            $company = Company::create([
                'account_id'        => $user->account_id,
                'created_by'        => $user->id,
                'name'              => $request->name,
                'industry'          => $request->industry,
                'country'           => $request->country,
                'timezone'          => $request->timezone
            ]);

            UserCompany::create([
                'company_id' => $company->id,
                'user_id'    => $user->id
            ]);
        }catch(Exception $e){
            DB::rollBack();

            throw $e;
        }

        DB::commit();

        return response($company, 201)
                ->withHeaders([
                    'Location' => $company->link
                ]);
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
        
        return response($company);
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
            'name'      => 'min:1|max:255',
            'industry'  => 'min:1|max:255',
            'country'   => [new CountryRule()],  
            'timezone'  => 'timezone'
        ];
        
        $validator = Validator::make($request->input(), $rules);
        if( $validator->fails() ){
            return response([
                'error' => $validator->errors()->first()
            ], 400);
        }

        if( $request->filled('name') )
            $company->name = $request->name;
        if( $request->filled('industry') )
            $company->industry = $request->industry;
        if( $request->filled('country') )
            $company->country = $request->country;
        if( $request->filled('timezone') )
            $company->timezone = $request->timezone;

        $company->save();

        return response($company);
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
