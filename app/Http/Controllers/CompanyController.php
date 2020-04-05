<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use \App\Models\Company;
use \App\Models\User;
use \App\Models\UserCompany;
use \App\Models\Company\Report;
use \App\Models\Company\ReportAutomation;
use \App\Models\Company\AudioClip;
use \App\Models\Company\PhoneNumberConfig;
use \App\Models\Company\Call;
use \App\Models\Company\CallRecording;
use \App\Models\BlockedPhoneNumber;
use \App\Models\BlockedPhoneNumber\BlockedCall;



use \App\Models\Company\PhoneNumberPool;
use \App\Rules\CountryRule;
use \App\Rules\BulkCompanyRule;
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
        $fields = [
            'companies.id',
            'companies.name',
            'companies.industry',
            'companies.country',
            'companies.created_at',
            'companies.updated_at'
        ];

        $user = $request->user();

        $query = DB::table('companies')
                    ->select(['companies.*', 'phone_number_pools.id AS phone_number_pool_id'])
                    ->leftJoin('phone_number_pools', 'phone_number_pools.company_id', 'companies.id')
                    ->where('companies.account_id', $user->account_id)
                    ->whereIn('companies.id', function($query) use($user){
                        $query->select('company_id')
                                ->from('user_companies')
                                ->where('user_id', $user->id);
                    })
                    ->whereNull('companies.deleted_at');
        
        return parent::results(
            $request,
            $query,
            [],
            $fields,
            'companies.created_at'
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

        $company->phone_number_pool_id = null;

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
        $pool = PhoneNumberPool::where('company_id', $company->id)->first();

        $company->phone_number_pool_id = $pool ? $pool->id : null;

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

        $pool = PhoneNumberPool::where('company', $company->id)->first();

        $company->phone_number_pool_id = $pool ? $pool->id : null;

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

    /**
     * Bulk Delete
     * 
     */
    public function bulkDelete(Request $request)
    {
        $validator = validator($request->input(), [
            'ids' => ['required','json', new BulkCompanyRule($request->user())]
        ]);

        if( $validator->fails() ){
            return response([
                'error' => $validator->errors()->first()
            ], 400);
        }

        $results = DB::table('companies')
                        ->select(['companies.id', 'companies.name', DB::raw('COUNT(phone_numbers.id) as phone_number_count')])
                        ->leftJoin('phone_numbers', 'phone_numbers.company_id', 'companies.id')
                        ->whereIn('companies.id', json_decode($request->ids))
                        ->groupBy('companies.id', 'companies.name')
                        ->get();

        $warnings = [];
        $errors   = [];
        $deleted  = [];
        foreach( $results as $result ){
            if( $result->phone_number_count ){
                $warnings[] = 'Company "' . $result->name . '" has attached phone numbers. Please detach and try again.';
                continue;
            }
            $deleted[] = $result->id;
        }

        if( count($deleted) ){
            Company::whereIn('id', $deleted)->delete();
            UserCompany::whereIn('company_id', $deleted)->delete();
            Report::whereIn('company_id', $deleted)->delete();
            ReportAutomation::whereIn('report_id', function($q) use($deleted){
                $q->select('id')
                    ->from('reports')
                    ->whereIn('reports.company_id', $deleted);
            })->delete();
            AudioClip::whereIn('company_id', $deleted)->delete();
            BlockedPhoneNumber::whereIn('company_id', $deleted)->delete();
            PhoneNumberConfig::whereIn('company_id', $deleted)->delete();
        }

        return response([
            'errors'   => $errors,
            'warnings' => $warnings,
            'deleted'  => $deleted
        ]);
    }

    /**
     * Export results
     * 
     */
    public function export(Request $request)
    {
        $fields = [
            'companies.id',
            'companies.name',
            'companies.industry',
            'companies.country',
            'companies.created_at',
            'companies.updated_at'
        ];
        
        return parent::exportResults(
            Company::class,
            $request,
            [],
            $fields,
            'companies.created_at'
        );
    }
}