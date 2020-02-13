<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use \App\Rules\Company\AudioClipRule;
use \App\Models\Company;
use App\Rules\Company\PhoneNumberConfigRule;
use App\Rules\Company\NumbersRule;
use App\Rules\Company\SingleWebsiteSessionPoolRule;
use App\Rules\ReferrerAliasesRule;
use App\Rules\SwapRulesRule;
use \App\Models\Company\AudioClip;
use \App\Models\Company\PhoneNumberPool;
use \App\Models\Company\PhoneNumber;
use \App\Models\Transaction;
use Validator;
use Exception;
use Log;
use DB;

class PhoneNumberPoolController extends Controller
{
    /**
     * List phone number pools
     * 
     * @param Request $company
     * @param Company $company
     * 
     * @return Response
     */
    public function list(Request $request, Company $company)
    {
        $rules = [
            'limit'     => 'numeric',
            'page'      => 'numeric',
            'order_by'  => 'in:name,created_at,updated_at',
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
        $orderBy    = $request->order_by  ?: 'created_at';
        $orderDir   = strtoupper($request->order_dir) ?: 'DESC';
        $search     = $request->search;
        
        $query = PhoneNumberPool::where('company_id', $company->id);
        
        if( $search ){
            $query->where(function($query) use($search){
                $query->where('name', 'like', '%' . $search . '%');
            });
        }

        if( $request->category )
            $query->where('category', $request->category);

        if( $request->sub_category )
            $query->where('sub_category', $request->sub_category);
        

        $resultCount = $query->count();
        $records     = $query->offset(( $page - 1 ) * $limit)
                             ->limit($limit)
                             ->orderBy($orderBy, $orderDir)
                             ->get();
                             
        $nextPage = null;
        if( $resultCount > ($page * $limit) )
            $nextPage = $page + 1;

        return response([
            'results'              => $records,
            'result_count'         => $resultCount,
            'limit'                => $limit,
            'page'                 => $page,
            'total_pages'          => ceil($resultCount / $limit),
            'next_page'            => $nextPage
        ]);
    }

    /**
     * Create a phone number pool
     * 
     * @param Request $request
     * @param Company $company
     * 
     * @return Response
     */
    public function create(Request $request, Company $company)
    {
        $rules = [
            'name'                   => 'bail|required|max:255',
            'size'                   => 'bail|required|numeric|min:5|max:50',  
            'phone_number_config_id' => [
                'bail',
                'required',
                (new PhoneNumberConfigRule($company))
            ],
            'swap_rules'    => [
                'bail', 
                'required', 
                'json', 
                new SwapRulesRule()
            ],    
            'referrer_aliases', [
                'bail', 
                'json', 
                new ReferrerAliasesRule()
            ],
            'toll_free'     => 'bail|boolean',
            'starts_with'   => 'bail|digits_between:1,10',
        ];

        $validator = Validator::make($request->input(), $rules);
        if( $validator->fails() ){
            return response([
                'error' =>  $validator->errors()->first()
            ], 400);
        }

        //  Make no pools exist for this company
        $existingPool = PhoneNumberPool::where('company_id', $company->id)->first();
        if( $existingPool ){
            return response([
                'error' => 'Only 1 online number pool allowed per company.'
            ], 400);
        }

        $user               = $request->user();
        $account            = $company->account;
        $purchaseObject     = 'PhoneNumber.' . ($request->toll_free ? 'TollFree' : 'Local'); 
        $poolSize           = intval($request->size);

        //  Make sure this account can purchase the amount of numbers requested
        if( ! $account->balanceCovers($purchaseObject, $poolSize, true) ){
                return response([
                    'error' => 'Your account balance(' 
                                . $account->rounded_balance 
                                . ') is too low to complete this purchase. '
                                . 'Reload account balance or turn on auto-reload in your account payment settings and try again. '
                                . 'If auto-reload is already on, your payment method may be invalid.'
                ], 400);
        }

            
        //  Make sure we have enough numbers available
        $availableNumbers = PhoneNumber::listAvailable(
            $request->starts_with, 
            $poolSize, 
            $request->toll_free ?: false, 
            $company->country
        );

        if( count($availableNumbers) < $poolSize ){
            if( $request->toll_free ){
                $error = 'Not enough toll free numbers available. Try using local numbers.';
            }else{
                $error = 'Not enough local numbers available. Try again with a different area code.';
            }

            return response([
                'error' => $error
            ], 400);
        }

        //  Step 1: Create Pool
        $swapRules       = json_decode($request->swap_rules);
        $referrerAliases = $request->referrer_aliases ? json_decode($request->referrer_aliases) : null;
    
        $pool = PhoneNumberPool::create([
            'company_id'                => $company->id,
            'user_id'                => $user->id,
            'phone_number_config_id'    => $request->phone_number_config_id,
            'name'                      => $request->name,
            'referrer_aliases'          => $referrerAliases,
            'swap_rules'                => $swapRules,
            'toll_free'                 => $request->toll_free ? true : false,
            'starts_with'               => $request->starts_with ?: null,
            'size'                      => 0
        ]);

        for($i = 0; $i < $request->size; $i++){
            try{
                //  Create Remote Resource
                $availableNumber = $availableNumbers[$i];
                $purchasedPhone = PhoneNumber::purchase($availableNumber->phoneNumber);

                //  Tie to company and make local record
                $phoneNumber = PhoneNumber::create([
                    'uuid'                      => Str::uuid(),
                    'external_id'               => $purchasedPhone->sid,
                    'phone_number_pool_id'      => $pool->id,
                    'company_id'                => $pool->company_id,
                    'user_id'                => $pool->user_id,
                    'toll_free'                 => $request->toll_free ? 1 : 0,
                    'country_code'              => PhoneNumber::countryCode($purchasedPhone->phoneNumber),
                    'number'                    => PhoneNumber::number($purchasedPhone->phoneNumber),
                    'voice'                     => $purchasedPhone->capabilities['voice'],
                    'sms'                       => $purchasedPhone->capabilities['sms'],
                    'mms'                       => $purchasedPhone->capabilities['mms'],
                    'name'                      => $purchasedPhone->phoneNumber
                ]);
                
                //  Log transaction while adjusting balance
                $account->transaction(
                    Transaction::TYPE_PURCHASE,
                    $purchaseObject,
                    $phoneNumber->getTable(),
                    $phoneNumber->id,
                    'Purchased Number ' . $purchasedPhone->phoneNumber,
                    $company->id,
                    $user->id
                );

                $pool->size++;
            }catch(Exception $e){

                Log::error($e->getTraceAsString());

                break; // Stop loop once we run into an issue
            }
        }

        $pool->save();

        $pool->phone_numbers; //    Attach phone numbers

        return response($pool, 201);
    }

    /**
     * View a phone number pool
     * 
     * @param Request $company
     * @param Company $company
     * @param PhoneNumberPool $phoneNumberPool
     * 
     * @return Response
     */
    public function read(Request $request, Company $company, PhoneNumberPool $phoneNumberPool)
    {
        $phoneNumberPool->phone_numbers;

        return response($phoneNumberPool);
    }

    /**
     * Update a phone number pool
     * 
     * @param Request $company
     * @param Company $company
     * @param PhoneNumberPool $phoneNumberPool
     * 
     * @return Response
     */
    public function update(Request $request, Company $company, PhoneNumberPool $phoneNumberPool)
    {
        $rules = [
            'name'              => 'bail|max:255',
            'size'              => 'bail|numeric|min:5|max:50',  
            'phone_number_config_id' => [
                'bail',
                (new PhoneNumberConfigRule($company))
            ],
            'swap_rules'    => [
                'bail', 
                'json', 
                new SwapRulesRule()
            ],    
            'referrer_aliases', [
                'bail', 
                'json', 
                new ReferrerAliasesRule()
            ],
            'toll_free'     => 'bail|boolean',
            'starts_with'   => 'bail|digits_between:1,10'
        ];

        $validator = Validator::make($request->input(), $rules);
        if( $validator->fails() ){
            return response([
                'error' =>  $validator->errors()->first()
            ], 400);
        }

        //  Update Pool
        if( $request->filled('phone_number_config_id') )
            $phoneNumberPool->phone_number_config_id = $request->phone_number_config_id;
        if( $request->filled('name') )
            $phoneNumberPool->name = $request->name;
        if( $request->filled('starts_with') )
            $phoneNumberPool->starts_with = $request->starts_with;
        if( $request->filled('toll_free') )
            $phoneNumberPool->toll_free = $request->toll_free;
        if( $request->filled('referrer_aliases') ){
            $referrerAliases = json_decode($request->referrer_aliases);
            $phoneNumberPool->referrer_aliases = $referrerAliases;
        }
        if( $request->filled('swap_rules') ){
            $swapRules = json_decode($request->swap_rules);
            $phoneNumberPool->swap_rules = $swapRules;
        }

        //  If the size hasn't changed, stop here
        $currentPoolSize    = count($phoneNumberPool->phone_numbers);
        if( ! $request->filled('size') || $request->size ==  $currentPoolSize ){
            $phoneNumberPool->save();

            $phoneNumberPool->phone_numbers; //    Attach phone numbers

            return response($phoneNumberPool, 200);
        }

        //  If there are numbers added, charge account
        $newPoolSize        = intval($request->size); 
        $account            = $company->account;
        $user               = $request->user();

        //  If we are purchasing numbers
        if( $newPoolSize > $currentPoolSize ){
            //  Make sure this account can purchase the amount of numbers requested
            $purchaseCount  = $newPoolSize - $currentPoolSize;
            $purchaseObject = 'PhoneNumber.' . ($phoneNumberPool->toll_free ? 'TollFree' : 'Local'); 
            if( ! $account->balanceCovers($purchaseObject, $purchaseCount, true) ){
                    return response([
                        'error' => 'Your account balance(' 
                                    . $account->rounded_balance 
                                    . ') is too low to complete this purchase. '
                                    . 'Reload account balance or turn on auto-reload in your account payment settings and try again. '
                                    . 'If auto-reload is already on, your payment method may be invalid.'
                    ], 400);
            }
            
            //  Make sure we have enough numbers available
            $availableNumbers = PhoneNumber::listAvailable(
                $phoneNumberPool->starts_with, 
                $purchaseCount, 
                $phoneNumberPool->toll_free, 
                $company->country
            );

            if( count($availableNumbers) < $purchaseCount ){
                if( $tollFree ){
                    $error = 'Not enough toll free numbers available. Try using local numbers.';
                }else{
                    $error = 'Not enough local numbers available. Try again with a different area code.';
                }

                return response([
                    'error' => $error
                ], 400);
            }

            //  Purchase Numbers and add to pool
            for($i = 0; $i < $purchaseCount; $i++){
                try{
                    //  Create Remote Resource
                    $availableNumber = $availableNumbers[$i];
                    $purchasedPhone = PhoneNumber::purchase($availableNumber->phoneNumber);
    
                    //  Tie to company and make local record
                    $phoneNumber = PhoneNumber::create([
                        'uuid'                      => Str::uuid(),
                        'external_id'               => $purchasedPhone->sid,
                        'phone_number_pool_id'      => $phoneNumberPool->id,
                        'company_id'                => $phoneNumberPool->company_id,
                        'user_id'                => $phoneNumberPool->user_id,
                        'toll_free'                 => $phoneNumberPool->toll_free ? 1 : 0,
                        'country_code'              => PhoneNumber::countryCode($purchasedPhone->phoneNumber),
                        'number'                    => PhoneNumber::number($purchasedPhone->phoneNumber),
                        'voice'                     => $purchasedPhone->capabilities['voice'],
                        'sms'                       => $purchasedPhone->capabilities['sms'],
                        'mms'                       => $purchasedPhone->capabilities['mms'],
                        'name'                      => $purchasedPhone->phoneNumber
                    ]);
                    
                    //  Log transaction
                    $account->transaction(
                        Transaction::TYPE_PURCHASE,
                        $purchaseObject,
                        $phoneNumber->getTable(),
                        $phoneNumber->id,
                        'Purchased Number ' . $purchasedPhone->phoneNumber,
                        $company->id,
                        $user->id
                    );
                }catch(Exception $e){
                    Log::error($e->getTraceAsString());
    
                    break; // Stop loop once we run into an issue
                }
            }
        }elseif( $newPoolSize < $currentPoolSize ){
            //  If we are releasing numbers
            $phoneNumbers = $phoneNumberPool->phone_numbers;
            $releaseCount = $currentPoolSize - $newPoolSize;
            $released     = [];
            $remaining    = [];
            foreach( $phoneNumbers as $phoneNumber ){
                if( count($released) >= $releaseCount )
                    break;
                    
                try{
                    $phoneNumber->release();

                    $released[] = $phoneNumber->id;
                }catch(Exception $e){
                    Log::error($e->getTraceAsString());

                    break;
                }
            }
        }


        $phoneNumberPool->save();

        $phoneNumberPool->load('phone_numbers');

        return response($phoneNumberPool);
    }

    /**
     * Delete a phone number pool
     * 
     * @param Request $company
     * @param Company $company
     * @param PhoneNumberPool $phoneNumberPool
     * 
     * @return Response
     */
    public function delete(Request $request, Company $company, PhoneNumberPool $phoneNumberPool)
    {
        //  Detach numbers then remove pool
        $phoneNumbers = PhoneNumber::where('phone_number_pool_id', $phoneNumberPool->id)
                                    ->get();
        
        foreach( $phoneNumbers as $phoneNumber ){
            $phoneNumber->release();
        }
       
        $phoneNumberPool->delete();

        return response([
            'message' => 'Deleted.'
        ]);
    }
}
