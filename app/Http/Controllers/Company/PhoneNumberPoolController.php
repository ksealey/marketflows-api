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
use \App\Models\Purchase;
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

        $records = $this->withAppendedDates($company, $records);

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
            'initial_pool_size'      => 'bail|required|numeric|min:5|max:20',  
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
        $poolSize           = intval($request->initial_pool_size);

        //  Make sure this account can purchase the amount of numbers requested
        if( ! $account->canPurchase($purchaseObject, $poolSize, true) ){
                return response([
                    'error' => 'Your account balance(' 
                                . $account->rounded_balance 
                                . ') is too low to complete purchase.'
                                . 'Reload account balance or turn on auto-reload in your account payment settings and try again.'
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
            'created_by'                => $user->id,
            'phone_number_config_id'    => $request->phone_number_config_id,
            'name'                      => $request->name,
            'referrer_aliases'          => $referrerAliases,
            'swap_rules'                => $swapRules
        ]);

        for($i = 0; $i < $request->initial_pool_size; $i++){
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
                    'created_by'                => $pool->created_by,
                    'phone_number_config_id'    => $pool->phone_number_config_id,
                    'category'                  => 'ONLINE',
                    'sub_category'              => 'WEBSITE_SESSION',
                    'toll_free'                 => $request->toll_free ? 1 : 0,
                    'country_code'              => PhoneNumber::countryCode($purchasedPhone->phoneNumber),
                    'number'                    => PhoneNumber::number($purchasedPhone->phoneNumber),
                    'voice'                     => $purchasedPhone->capabilities['voice'],
                    'sms'                       => $purchasedPhone->capabilities['sms'],
                    'mms'                       => $purchasedPhone->capabilities['mms'],
                    'name'                      => $purchasedPhone->phoneNumber
                ]);
                
                //  Log purchase while adjusting balance
                $account->purchase(
                    $company->id,
                    $user->id,
                    $purchaseObject,
                    $purchasedPhone->phoneNumber,
                    $phoneNumber->id,
                    $purchasedPhone->sid
                );
            }catch(Exception $e){

                Log::error($e->getTraceAsString());

                break; // Stop loop once we run into an issue
            }
        }

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
            'name'                   => 'bail|max:255',
            'phone_number_config_id' => [
                'bail',
                (new PhoneNumberConfigRule($company))
            ]
        ];

        $validator = Validator::make($request->input(), $rules);

        //  Require a category when the subcategory is set
        $validator->sometimes('category', ['bail', 'required', 'in:ONLINE,OFFLINE'], function($input){
            return $input->filled('sub_category');
        });

        //  Make sure the sub_category is valid for the category
        $validator->sometimes('sub_category', ['bail', 'required', 'in:WEBSITE_MANUAL,WEBSITE,WEBSITE_SESSION,SOCIAL_MEDIA,EMAIL'], function($input){
            return $input->category === 'ONLINE';
        });
        $validator->sometimes('sub_category', ['bail', 'required', 'in:TV,RADIO,NEWSPAPER,DIRECT_MAIL,FLYER,OTHER'], function($input){
            return $input->category === 'OFFLINE';
        });

        //  Only allow a single website session pool for a company
        $validator->sometimes('sub_category', ['bail', 'required', new SingleWebsiteSessionPoolRule($company)], function($input){
            return $input->sub_category === 'WEBSITE_SESSION';
        });

        //  Make sure the swap rules are there and valid when it's for a website or website sessions
        $validator->sometimes('swap_rules', ['bail', 'required', 'json', new SwapRulesRule()], function($input){
            return $input->sub_category == 'WEBSITE' || $input->sub_category == 'WEBSITE_SESSION';
        });

        //  Require a source for all except web sessions
        $validator->sometimes('source', ['bail', 'required', 'max:255'], function($input){
            return $input->sub_category !== 'WEBSITE_SESSION';
        });

        //  Require source_param for web sessions
        $validator->sometimes('source_param', ['bail', 'required', 'max:255'], function($input){
            return $input->sub_category === 'WEBSITE_SESSION';
        });

        //  Validate referrer aliases when provided
        $validator->sometimes('referrer_aliases', ['bail', 'json', new ReferrerAliasesRule()], function($input){
            return $input->sub_category === 'WEBSITE_SESSION';
        });

        if( $validator->fails() ){
            return response([
                'error' =>  $validator->errors()->first()
            ], 400);
        }

       
        if( $request->filled('phone_number_config_id') )
            $phoneNumberPool->phone_number_config_id = $request->phone_number_config_id;
        if( $request->filled('name') )
            $phoneNumberPool->name = $request->name;
        if( $request->filled('category') )
            $phoneNumberPool->category = $request->category;
        if( $request->filled('sub_category') )
            $phoneNumberPool->sub_category = $request->sub_category;
        if( $request->filled('source') )
            $phoneNumberPool->source = $request->sub_category == 'WEBSITE_SESSION' ? null : $request->source;
        if( $request->filled('source_param') )
            $phoneNumberPool->source_param = $request->sub_category == 'WEBSITE_SESSION' ? $request->source_param : null;
        if( $request->filled('referrer_aliases') ){
            $referrerAliases = $request->sub_category  == 'WEBSITE_SESSION' ? json_decode($request->referrer_aliases) : null;
        
            $phoneNumberPool->referrer_aliases = $referrerAliases;
        }
        if( $request->filled('referrer_aliases') ){
            $swapRules = ($request->sub_category == 'WEBSITE_SESSION' || $request->sub_category == 'WEBSITE') ? json_decode($request->swap_rules) : null;

            $phoneNumberPool->swap_rules = $swapRules;
        }

        $phoneNumberPool->save();

        $phoneNumberPool->phone_numbers; // Attach phone numbers

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
        if( $phoneNumberPool->isInUse() ){
            return response([
                'error' => 'This phone number pool is in use - release or re-assign all attached phone numbers and try again.'
            ], 400);
        }

        //  Detach phone numbers from pool
        PhoneNumber::where('phone_number_pool_id', $phoneNumberPool->id)
                   ->update(['phone_number_pool_id' => null]);

        $phoneNumberPool->delete();

        return response([
            'message' => 'deleted'
        ]);
    }
}
