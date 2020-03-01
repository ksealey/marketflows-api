<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use \App\Models\Company;
use \App\Models\User;
use \App\Models\UserCompany;
use \App\Rules\CountryRule;
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
        $user  = $request->user();

        $query = Company::where('account_id', $user->account_id)
                        ->whereIn('id', function($query) use($user){
                            $query->select('company_id')
                                  ->from('user_companies')
                                  ->where('user_id', $user->id);
                        });
        
        if( $request->search )
            $query->where('name', 'like', '%' . $request->search . '%');

        return parent::results(
            $request,
            $query,
            [ 'order_by'  => 'in:name,industry,created_at,updated_at' ]
        );
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
            'name'      => 'bail|required|max:64',
            'industry'  => 'bail|required|max:64',
            'country'   => ['bail', 'required', new CountryRule()]
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
                'user_id'           => $user->id,
                'name'              => $request->name,
                'industry'          => $request->industry,
                'country'           => $request->country
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

        return response($company, 201);
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
            'name'      => 'min:1|max:64',
            'industry'  => 'min:1|max:64',
            'country'   => [new CountryRule()]
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
        //  Remove any users attached
        UserCompany::where('company_id', $company->id)
                   ->delete();

        $company->delete();

        return response([
            'message' => 'deleted'
        ]);
    }
}
