<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use \App\Models\User;
use \App\Models\Company;
use \App\Models\Company\AudioClip;
use \App\Models\Company\Call;
use \App\Models\Company\CallRecording;
use \App\Models\Company\PhoneNumber;
use \App\Models\Company\PhoneNumberConfig;
use \App\Rules\CountryRule;
use \App\Rules\ParamNameRule;
use \App\Jobs\DeleteCompanyJob;
use Validator;
use Exception;
use DB;

class CompanyController extends Controller
{
    /* Listable fields */
    protected $fields = [
        'companies.id',
        'companies.name',
        'companies.industry',
        'companies.country',
        'companies.created_at',
        'companies.updated_at'
    ];

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
        $query = Company::select([
            'companies.*'
        ])->where('companies.account_id', $user->account_id);   
        
        return parent::results(
            $request,
            $query,
            [],
            $this->fields,
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
        $config    = config('services.twilio.languages');
        $languages = array_keys($config);
        $voiceKey  = $request->tts_language && in_array($request->tts_language, $languages) ? $request->tts_language : 'en-US';
        $voices    = array_keys($config[$voiceKey]['voices']); 

        $rules = [
            'name'                       => 'bail|required|max:64',
            'industry'                   => 'bail|required|max:64',
            'country'                    => ['bail', 'required', new CountryRule()],
            'tts_language'               => 'bail|in:' . implode(',', $languages),
            'tts_voice'                  => ['bail', 'required_with:tts_language', 'in:' . implode(',', $voices)],
            'source_param'               => ['bail', new ParamNameRule()],
            'medium_param'               => ['bail', new ParamNameRule()],
            'content_param'              => ['bail', new ParamNameRule()],
            'campaign_param'             => ['bail', new ParamNameRule()],
            'keyword_param'              => ['bail', new ParamNameRule()],
            'tracking_expiration_days'   => ['bail', 'numeric', 'min:0', 'max:9999'],
        ];

        $validator = validator($request->input(), $rules);
        if( $validator->fails() ){
            return response([
                'error' => $validator->errors()->first()
            ], 400);
        }

        $user    = $request->user();
        $account = $user->account;

        //  Create company using account defaults if values not provided
        $company = Company::create([
            'account_id'                    => $user->account_id,
            'user_id'                       => $user->id,
            'name'                          => $request->name,
            'industry'                      => $request->industry,
            'country'                       => $request->country,
            'tts_voice'                     => $request->tts_voice ?: $account->tts_voice,
            'tts_language'                  => $request->tts_language ?: $account->tts_language,
            'source_param'                  => $request->source_param ?: $account->source_param,
            'medium_param'                  => $request->medium_param ?: $account->medium_param,
            'content_param'                 => $request->content_param ?: $account->content_param,
            'campaign_param'                => $request->campaign_param ?: $account->campaign_param,
            'keyword_param'                 => $request->keyword_param ?: $account->keyword_param,
            'tracking_expiration_days'      => $request->filled('tracking_expiration_days') ? intval($request->tracking_expiration_days) : 30,
            'created_by'                    => $user->id,
            'updated_by'                    => null     
        ]);
       
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
        $config    = config('services.twilio.languages');
        $languages = array_keys($config);
        $voiceKey  = $request->tts_language && in_array($request->tts_language, $languages) ? $request->tts_language : 'en-US';
        $voices    = array_keys($config[$voiceKey]['voices']); 
        $rules = [
            'name'          => 'min:1|max:64',
            'industry'      => 'min:1|max:64',
            'country'       => [new CountryRule()],
            'tts_language'  => 'bail|in:' . implode(',', $languages),
            'tts_voice'     => ['bail', 'required_with:tts_language', 'in:' . implode(',', $voices)],
            'source_param'  => ['bail', new ParamNameRule()],
            'medium_param'  => ['bail', new ParamNameRule()],
            'content_param' => ['bail', new ParamNameRule()],
            'campaign_param'    => ['bail', new ParamNameRule()],
            'keyword_param'     => ['bail', new ParamNameRule()],
        ];

        $validator = validator($request->input(), $rules);
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
        if( $request->filled('source_param') )
            $company->source_param = $request->source_param;
        if( $request->filled('medium_param') )
            $company->medium_param = $request->medium_param;
        if( $request->filled('content_param') )
            $company->content_param = $request->content_param;
        if( $request->filled('campaign_param') )
            $company->campaign_param = $request->campaign_param;
        if( $request->filled('keyword_param') )
            $company->keyword_param = $request->keyword_param;
        if( $request->filled('tracking_expiration_days') )
            $company->tracking_expiration_days = intval($request->tracking_expiration_days);

        $user = $request->user();

        $company->updated_by = $user->id;
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
        $user = $request->user();

        //  Push a job to delete company resources. Phone number, files, etc.
        DeleteCompanyJob::dispatch($user, $company, true);
        
        //  Soft delete company and tie to user that deleted it
        $company->deleted_by = $user->id;
        $company->deleted_at = now();
        $company->save();

        return response([ 'message' => 'Deleted' ]);
    }

    /**
     * Export results
     * 
     * @param Request $request
     * 
     * @return Response
     */
    public function export(Request $request)
    {
        return parent::exportResults(
            Company::class,
            $request,
            [],
            $this->fields,
            'companies.created_at'
        );
    }
}