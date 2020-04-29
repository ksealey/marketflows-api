<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;


use \App\Models\User;
use \App\Models\Company;
use \App\Models\UserCompany;
use \App\Models\BlockedPhoneNumber;
use \App\Models\BlockedPhoneNumber\BlockedCall;
use \App\Models\Company\Report;
use \App\Models\Company\ReportAutomation;
use \App\Models\Company\AudioClip;
use \App\Models\Company\Call;
use \App\Models\Company\CallRecording;
use \App\Models\Company\PhoneNumber;
use \App\Models\Company\PhoneNumberPool;
use \App\Models\Company\PhoneNumberConfig;

use \App\Rules\CountryRule;
use \App\Rules\BulkCompanyRule;

use \App\Events\CompanyEvent;
use \App\Events\Company\PhoneNumberEvent;
use \App\Events\Company\PhoneNumberPoolEvent;
use \App\Events\Company\BlockedPhoneNumberEvent;
use \App\Events\Company\PhoneNumberConfigEvent;
use \App\Events\Company\AudioClipEvent;

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

        $query = Company::where('companies.account_id', $user->account_id)
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

        $user    = $request->user();
        $account = $user->account;

        DB::beginTransaction();

        try{
            $company = Company::create([
                'account_id'        => $user->account_id,
                'user_id'           => $user->id,
                'name'              => $request->name,
                'industry'          => $request->industry,
                'country'           => $request->country,
                'tts_voice'         => $account->default_tts_voice ?: 'Joanna',
                'tts_language'      => $account->default_tts_language ?: 'en-US'        
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

        event(new CompanyEvent($user, [$company], 'create')); 

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
        $tts       = config('services.twilio');
        $languages = $tts['languages'];
        $voices    = !empty($languages[$request->tts_language]) 
                        ? array_keys($languages[$request->tts_language]['voices']) 
                        : [];
        $rules = [
            'name'          => 'min:1|max:64',
            'industry'      => 'min:1|max:64',
            'country'       => [new CountryRule()],
            'tts_language'  => 'bail|in:' . implode(',', array_keys($languages)),
        ];

        $validator = Validator::make($request->input(), $rules);
        $validator->sometimes('tts_voice', ['bail', 'required', 'in:' . implode(',', $voices)], function($input){
            return $input->filled('tts_language');
        });

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
        if( $request->filled('tts_voice') )
            $company->tts_voice = $request->tts_voice;
        if( $request->filled('tts_language') )
            $company->tts_language = $request->tts_language;

        $company->save();

        event(new CompanyEvent($request->user(), [$company], 'update'));

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
        $account = $company->account;

        $company->delete();

        //  Remove all links from users to deleted companies
        UserCompany::where('company_id', $company->id)->delete();

        $phoneNumbers = PhoneNumber::where('company_id', $company->id)->get();
        PhoneNumber::where('company_id', $company->id)->delete();

        $pools = PhoneNumberPool::where('company_id', $company->id)->get();
        PhoneNumberPool::where('company_id', $company->id)->delete();

        //  Remove phone number configs
        $configs = PhoneNumberConfig::where('company_id', $company->id)->get();
        PhoneNumberConfig::where('company_id', $company->id)->delete();

        //  Remove audio clips
        $audioClips = AudioClip::where('company_id', $company->id)->get();
        AudioClip::where('company_id', $company->id)->delete();

        //  Remove Blocked Phone Numbers
        $blockedPhoneNumbers = BlockedPhoneNumber::where('company_id', $company->id)->get();
        BlockedPhoneNumber::where('company_id', $company->id)->delete();

        //  Remove all reports for company
        $reports = Report::where('company_id', $company->id)->get();
        ReportAutomation::whereIn('report_id', function($q) use($company){
            $q->select('id')
              ->from('reports')
              ->where('reports.company_id', $company->id);
        })->delete();
         
        $user = $request->user();

        event(new CompanyEvent($user, [$company], 'delete'));

        if( count($phoneNumbers) )
            event(new PhoneNumberEvent($account, $phoneNumbers, 'delete'));

        if( count($pools) )
            event(new PhoneNumberPoolEvent($user, $pools, 'delete'));

        if( count($configs) )
            event(new PhoneNumberConfigEvent($user, $configs, 'delete'));

        if( count($audioClips) )
            event(new AudioClipEvent($user, $audioClips, 'delete'));
        
        if( count($blockedPhoneNumbers) )
            event(new BlockedPhoneNumberEvent($user, $blockedPhoneNumbers, 'delete'));
        
        if( count($reports) )
            event(new ReportEvent($user, $reports, 'delete'));

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

        $companyIds = json_decode($request->ids);
        $companies  = Company::whereIn('companies.id', $companyIds)->get();

        if( count($companies) ){
            //  Get list of deleted companies, then delete
            Company::whereIn('id', $companyIds)->delete();

            //  Remove all links from users to deleted companies
            UserCompany::whereIn('company_id', $companyIds)->delete();

            $phoneNumbers = PhoneNumber::whereIn('company_id', $companyIds)->get();
            PhoneNumber::whereIn('company_id', $companyIds)->delete();

            $pools = PhoneNumberPool::whereIn('company_id', $companyIds)->get();
            PhoneNumberPool::whereIn('company_id', $companyIds)->delete();

            //  Remove phone number configs
            $configs = PhoneNumberConfig::whereIn('company_id', $companyIds)->get();
            PhoneNumberConfig::whereIn('company_id', $companyIds)->delete();

            //  Remove audio clips
            $audioClips = AudioClip::whereIn('company_id', $companyIds)->get();
            AudioClip::whereIn('company_id', $companyIds)->delete();

            //  Remove Blocked Phone Numbers
            $blockedPhoneNumbers = BlockedPhoneNumber::whereIn('company_id', $companyIds)->get();
            BlockedPhoneNumber::whereIn('company_id', $companyIds)->delete();

            //  Remove all reports for company
            $reports = Report::whereIn('company_id', $companyIds)->get();
            Report::whereIn('company_id', $companyIds)->delete();
            ReportAutomation::whereIn('report_id', function($q) use($companyIds){
                $q->select('id')
                  ->from('reports')
                  ->whereIn('reports.company_id', $companyIds);
            })->delete();
             
            $user = $request->user();

            if( count($companies) )
                event(new CompanyEvent($user, $companies, 'delete'));

            if( count($phoneNumbers) )
                event(new PhoneNumberEvent($user->account, $phoneNumbers, 'delete'));

            if( count($pools) )
                event(new PhoneNumberPoolEvent($user, $pools, 'delete'));

            if( count($configs) )
                event(new PhoneNumberConfigEvent($user, $configs, 'delete'));

            if( count($audioClips) )
                event(new AudioClipEvent($user, $audioClips, 'delete'));
            
            if( count($blockedPhoneNumbers) )
                event(new BlockedPhoneNumberEvent($user, $blockedPhoneNumbers, 'delete'));
            
            if( count($reports) )
                event(new ReportEvent($user, $reports, 'delete'));
        }

        return response([
            'message' => 'Deleted.'
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