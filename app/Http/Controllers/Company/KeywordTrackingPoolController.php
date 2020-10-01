<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\Company;
use App\Models\Company\PhoneNumber;
use App\Models\Company\KeywordTrackingPool;
use App\Services\PhoneNumberService;
use App\Rules\SwapRulesRule;
use App\Rules\Company\PhoneNumberConfigRule;
use App\Jobs\DeleteKeywordTrackingPoolJob;
use Exception;
use DB;

class KeywordTrackingPoolController extends Controller
{
    public $phoneNumberService;

    public function __construct(PhoneNumberService $phoneNumberService)
    {
        $this->phoneNumberService = $phoneNumberService;
    }

    public function list(Request $request, Company $company)
    {
        //  Build Query
        $query = KeywordTrackingPool::where('keyword_tracking_pools.company_id', $company->id);
                    
        //  Pass along to parent for listing
        return parent::results(
            $request,
            $query,
            [],
            KeywordTrackingPool::accessibleFields(),
            'keyword_tracking_pools.created_at'
        );
    }

    public function create(Request $request, Company $company)
    {
        if( $company->keyword_tracking_pool ){
            return response([
                'error' => 'Only 1 keyword tracking pool is allowed per company'
            ], 400);
        }

        $rules = [
            'name'                => 'bail|required|max:64',
            'phone_number_config_id' => [
                'bail',
                'required',
                (new PhoneNumberConfigRule($company))
            ],
            'type'       => 'bail|required|in:Toll-Free,Local',
            'swap_rules' => ['bail', 'required', 'json', new SwapRulesRule()],
            'pool_size'  => 'required|numeric|min:5|max:20',
        ];

        $validator = validator($request->input(), $rules);
        $validator->sometimes('starts_with', ['bail', 'required', 'digits_between:1,10'], function($input){
            return $input->type === 'Local';
        });

        if( $validator->fails() ){
            return response([
                'error' => $validator->errors()->first()
            ], 400);
        }

        $user = $request->user();

        //
        //  Create pool
        //
        DB::beginTransaction();
        $keywordTrackingPool = KeywordTrackingPool::create([
            'uuid'                   => Str::uuid(),
            'account_id'             => $company->account_id,
            'company_id'             => $company->id,
            'phone_number_config_id' => $request->phone_number_config_id,
            'name'                   => $request->name,
            'swap_rules'             => $request->swap_rules,
            'created_by'             => $user->id
        ]);

        //
        //  Purchase phone numbers
        //
        $startsWith   = $request->type === 'Local' ? $request->starts_with : '';
        $poolSize     = intval($request->pool_size);
        $foundNumbers = $this->phoneNumberService->listAvailable(
            $startsWith, 
            $poolSize, 
            $request->type, 
            $company->country
        ) ?: [];

        if( ! count($foundNumbers) )
            return response([
                'error' => 'No phone numbers could be found for purchase. Please try again with a different search.'
            ], 400);

        if( count($foundNumbers) < $poolSize ){
            return response([
                'error' => count($foundNumbers)  
                            . ' number(s) were found for your search in your company\'s country(' 
                            . $company->country . ').'
            ], 400);
        }

        $purchaseCount = 0;
        for( $i = 0; $i < $poolSize; $i++ ){
            try{
                $purchasedPhone = $this->phoneNumberService
                                       ->purchase($foundNumbers[$i]->phoneNumber);

                $phoneNumber = PhoneNumber::create([
                    'uuid'                      => Str::uuid(),
                    'external_id'               => $purchasedPhone->sid,
                    'account_id'                => $company->account_id,
                    'company_id'                => $company->id,
                    'phone_number_config_id'    => $request->phone_number_config_id,
                    'keyword_tracking_pool_id'  => $keywordTrackingPool->id,
                    'category'                  => 'ONLINE',
                    'sub_category'              => 'WEBSITE',
                    'type'                      => $request->type,
                    'country_code'              => PhoneNumber::countryCode($purchasedPhone->phoneNumber),
                    'number'                    => PhoneNumber::number($purchasedPhone->phoneNumber),
                    'voice'                     => $purchasedPhone->capabilities['voice'],
                    'sms'                       => $purchasedPhone->capabilities['sms'],
                    'mms'                       => $purchasedPhone->capabilities['mms'],
                    'name'                      => $purchasedPhone->phoneNumber,
                    'swap_rules'                => $request->swap_rules,
                    'purchased_at'              => now(),
                    'created_by'                => $user->id
                ]);

                $purchaseCount++;
            }catch(Exception $e){
                continue;
            }
        }

        if( ! $purchaseCount ){
             DB::rollBack();

             return response([
                 'error' => 'No phone numbers could be allocated for your search at this time - Please try again later'
             ], 500);
        }

        DB::commit();

        if( $purchaseCount < $poolSize ){
            $keywordTrackingPool->error = $purchaseCount . 'Your keyword tracking pool has been created but only ' . $purchaseCount . ' numbers were available - You can add additional numbers at any time';
        }

        $keywordTrackingPool->phone_numbers = $keywordTrackingPool->phone_numbers;

        return response($keywordTrackingPool, 201);
    }

    public function read(Request $request, Company $company, KeywordTrackingPool $keywordTrackingPool)
    {
        return response($keywordTrackingPool);
    }

    public function update(Request $request, Company $company, KeywordTrackingPool $keywordTrackingPool)
    {
        $rules = [
            'name' => 'bail|max:64',
            'phone_number_config_id' => [
                'bail',
                (new PhoneNumberConfigRule($company))
            ],
            'swap_rules' => ['bail', 'json', new SwapRulesRule()],
        ];

        $validator = validator($request->input(), $rules);
        if( $validator->fails() ){
            return response([
                'error' => $validator->errors()->first()
            ], 400);
        }

        if( $request->filled('name') ){
            $keywordTrackingPool->name = $request->name;
        }
        
        if( $request->filled('phone_number_config_id') ){
            $keywordTrackingPool->phone_number_config_id = $request->phone_number_config_id;
        }

        if( $request->filled('swap_rules') ){
            $keywordTrackingPool->swap_rules = $request->swap_rules;
        }

        $keywordTrackingPool->save();

        PhoneNumber::where('keyword_tracking_pool_id', $keywordTrackingPool->id)
                    ->whereNull('deleted_at')
                    ->update([
                        'phone_number_config_id' => $keywordTrackingPool->phone_number_config_id,
                        'swap_rules'             => json_encode($keywordTrackingPool->swap_rules),
                    ]);

        $keywordTrackingPool->phone_numbers = $keywordTrackingPool->phone_numbers;

        return response($keywordTrackingPool);
    }

    public function delete(Request $request, Company $company, KeywordTrackingPool $keywordTrackingPool)
    {
        DeleteKeywordTrackingPoolJob::dispatch(
            $request->user(), 
            $keywordTrackingPool, 
            $request->release_numbers ?: false
        );

        return response([
            'message' => 'Delete queued'
        ]);
    }
}
