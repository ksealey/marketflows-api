<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use \App\Models\Company;
use \App\Models\User;
use \App\Models\UserCompany;
use \App\Rules\CompanyWebhookActionsRule;
use Validator;

class CompanyController extends Controller
{
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
                             ->get();

        return response([
            'message'         => 'success',
            'companies'       => $records,
            'result_count'    => $resultCount,
            'limit'           => $limit,
            'page'            => $page + 1,
            'total_pages'     => ceil($resultCount / $limit)
        ]);
    }

    public function create(Request $request)
    {
        $rules = [
            'name'            => 'required|max:255',
            'webhook_actions' => ['required', 'json', new CompanyWebhookActionsRule()]
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
            'name'              => $request->name,
            'webhook_actions'   => $request->webhook_actions
        ]);

        return response([
            'message' => 'created',
            'company' => $company
        ], 201);
    }

    public function read(Request $request, Company $company)
    {
        return response([
            'message' => 'success',
            'company' => $company
        ]);
    }

    public function update(Request $request, Company $company)
    {
        $rules = [
            'name'            => 'required|max:255',
            'webhook_actions' => ['required', 'json', new CompanyWebhookActionsRule()]
        ];
        
        $validator = Validator::make($request->input(), $rules);
        if( $validator->fails() ){
            return response([
                'error' => $validator->errors()->first()
            ], 400);
        }

        $company->name            = $request->name;
        $company->webhook_actions = $request->webhook_actions;
        $company->save();

        return response([
            'message' => 'success',
            'company' => $company
        ]);
    }

    public function delete(Request $request, Company $company)
    {
        //
        //  TODO: Make sure no records are attached
        //  ...
        //

        //  Remove from users
        User::where('company_id', $company->id)
             ->update(['company_id' => null]);

        UserCompany::where('company_id', $company->id)
                    ->delete();

        $company->delete();

        return response([
            'message' => 'deleted'
        ]);
    }
}
