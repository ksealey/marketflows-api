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

        $user  = $request->user();
        $query = Company::select([
            'companies.*'
        ])->where('companies.account_id', $user->account_id);   
        
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
        $config    = config('services.twilio.languages');
        $languages = array_keys($config);
        $voiceKey  = $request->tts_language && in_array($request->tts_language, $languages) ? $request->tts_language : 'en-US';
        $voices    = array_keys($config[$voiceKey]['voices']); 

        $rules = [
            'name'      => 'bail|required|max:64',
            'industry'  => 'bail|required|max:64',
            'country'   => ['bail', 'required', new CountryRule()],
            'ga_id'     => ['bail', 'nullable', 'regex:/^\bUA-\b[0-9]{6,10}\-[0-9]{1,4}$/'],
            'tts_language'  => 'bail|in:' . implode(',', $languages),
            'tts_voice'     => ['bail', 'required_with:tts_language', 'in:' . implode(',', $voices)]
        ];

        $validator = validator($request->input(), $rules);
        if( $validator->fails() ){
            return response([
                'error' => $validator->errors()->first()
            ], 400);
        }

        $user    = $request->user();
        $account = $user->account;

        $company = Company::create([
            'account_id'        => $user->account_id,
            'user_id'           => $user->id,
            'name'              => $request->name,
            'industry'          => $request->industry,
            'country'           => $request->country,
            'ga_id'             => $request->ga_id ?: null,
            'tts_voice'         => $request->tts_voice ?: $account->tts_voice,
            'tts_language'      => $request->tts_language ?: $account->tts_language,
            'created_by'        => $user->id,
            'updated_by'        => null     
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
            'ga_id'         => ['bail', 'nullable', 'regex:/^\bUA-\b[0-9]{6,10}\-[0-9]{1,4}$/'],
            'tts_language'  => 'bail|in:' . implode(',', $languages),
            'tts_voice'     => ['bail', 'required_with:tts_language', 'in:' . implode(',', $voices)]
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
        if( $request->has('ga_id') )
            $company->ga_id = $request->ga_id ?: null;
        if( $request->filled('tts_voice') )
            $company->tts_voice = $request->tts_voice;
        if( $request->filled('tts_language') )
            $company->tts_language = $request->tts_language;

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

        $user->deleteCompany($company);

        $company->deleted_by = $user->id;
        $company->deleted_at = now();
        $company->save();

        return response([
            'message' => 'Deleted'
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