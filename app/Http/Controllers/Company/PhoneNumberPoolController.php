<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use \App\Rules\Company\AudioClipRule;
use \App\Models\Company;
use App\Rules\Company\PhoneNumberConfigRule;
use App\Rules\Company\SingleWebsiteSessionPoolRule;
use App\Rules\SwapRulesRule;
use \App\Models\Company\AudioClip;
use \App\Models\Company\PhoneNumberPool;
use \App\Models\Company\PhoneNumber;
use \App\Models\BankedPhoneNumber;
use \App\Events\Company\PhoneNumberEvent;
use \App\Events\Company\PhoneNumberPoolEvent;
use App\Helpers\PhoneNumberManager;
use Validator;
use Exception;
use Log;
use DB;

class PhoneNumberPoolController extends Controller
{
    private $numberManager;

    //  Pass along to parent for listing
    static $fields = [
        'phone_number_pools.company_id',
        'phone_number_pools.name',
        'phone_numbers.disabled_at',
        'phone_numbers.created_at',
        'phone_numbers.updated_at'
    ];

    static $numberFields = [
        'phone_numbers.name',
        'phone_numbers.number',
        'phone_numbers.disabled_at',
        'phone_numbers.created_at',
        'phone_numbers.updated_at',
        'phone_numbers.assignments',
        'call_count',
    ];

    public function __construct(PhoneNumberManager $numberManager)
    {
        $this->numberManager = $numberManager;
    }

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
        $query = PhoneNumberPool::where('company_id', $company->id);

        //  Pass along to parent for listing
        return parent::results(
            $request,
            $query,
            [],
            self::$fields,
            'phone_number_pools.created_at'
        );
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
            'name'                   => 'bail|required|max:64',
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
            'override_campaigns'    => 'bail|boolean',
            'type'                  => 'bail|required|in:Local,Toll-Free',
            'starts_with'           => 'bail|nullable|digits_between:1,10',
            'disabled'              => 'bail|nullable|boolean'
        ];

        $validator = Validator::make($request->input(), $rules);
        if( $validator->fails() ){
            return response([
                'error' =>  $validator->errors()->first()
            ], 400);
        }

        //  Make sure no pools exist for this company
        if( PhoneNumberPool::where('company_id', $company->id)->count() ){
            return response([
                'error' => 'Only 1 keyword tracking pool allowed per company.'
            ], 400);
        }

        $user       = $request->user(); 
        $account    = $company->account; 
        $poolSize   = intval($request->size);
        $startsWith = $request->starts_with;
        $type       = $request->type;

        if( ! $account->canPurchaseNumbers($poolSize) ){
            return response([
                'error' => 'Unable to purchase ' . $poolSize . ' number(s) for this account - Verify a valid payment method has been added and try again.'
            ], 400);
        }
       
        //  Check bank for phone numbers
        $bankedNumbers = BankedPhoneNumber::availableNumbers(
            $user->account_id, 
            $company->country, 
            $type, 
            $startsWith, 
            $poolSize
        );

        $totalNumbersFound = count($bankedNumbers);
        $availableNumbers  = [];
        if( $totalNumbersFound < $poolSize ){
            //  Make sure we have enough numbers available
            $availableNumbers = $this->numberManager
                                     ->listAvailable(
                                        $startsWith, 
                                        $poolSize, 
                                        $type, 
                                        $company->country
                                     );
            $totalNumbersFound += count($availableNumbers);
        }
           
        if( $totalNumbersFound < $poolSize ){
            return response([
                'error' => 'Not enough ' . $type . ' numbers available. Try again with a different number type or area code.'
            ], 400);
        }
        
        //  Create Pool
        DB::beginTransaction();
        $phoneNumberPool = PhoneNumberPool::create([
            'account_id'                => $company->account_id,
            'company_id'                => $company->id,
            'created_by'                => $user->id,
            'phone_number_config_id'    => $request->phone_number_config_id,
            'name'                      => $request->name,
            'swap_rules'                => json_decode($request->swap_rules),
            'override_campaigns'        => !!$request->override_campaigns,
            'starts_with'               => $startsWith ?: null,
            'disabled_at'               => $request->disabled ? now() : null,
        ]);
        
        //
        //  Use banked phone numbers first
        //
        $inserts = [];
        $now     = now();
        foreach( $bankedNumbers as $bankedNumber ){
            //  Add to phone number insert list
            $inserts[] = [
                'uuid'                      => Str::uuid(),
                'phone_number_pool_id'      => $phoneNumberPool->id,
                'external_id'               => $bankedNumber->external_id,
                'account_id'                => $company->account_id,
                'company_id'                => $company->id,
                'created_by'                => $user->id,
                'phone_number_config_id'    => $phoneNumberPool->phone_number_config_id,
                'category'                  => 'ONLINE',
                'sub_category'              => 'WEBSITE',
                'type'                      => $bankedNumber->type,
                'country'                   => $bankedNumber->country,
                'country_code'              => $bankedNumber->country_code,
                'number'                    => $bankedNumber->number,
                'voice'                     => $bankedNumber->voice,
                'sms'                       => $bankedNumber->sms,
                'mms'                       => $bankedNumber->mms,
                'name'                      => $bankedNumber->country_code . $bankedNumber->number,
                'source'                    => '-',
                'medium'                    => null,
                'content'                   => null,
                'campaign'                  => null,
                'swap_rules'                => json_encode($phoneNumberPool->swap_rules),
                'purchased_at'              => $bankedNumber->purchased_at,
                'created_at'                => $now,
                'updated_at'                => $now,
            ];
        }
        
        //  Write banked phone numbers
        if( count($bankedNumbers) ){
            $deleteIds = array_column($bankedNumbers->toArray(), 'id');
            try{
                BankedPhoneNumber::whereIn('id', $deleteIds)->delete();
            }catch(Exception $e){
                Log::error($e->getTraceAsString());
                DB::rollBack();
                return response([
                    'error' => $e->getMessage()
                ], 500);
            }
        }
             
        //  See if we need more numbers
        $numbersNeeded = $poolSize - count($inserts); 
        if( $numbersNeeded ){
            for( $i = 0; $i < $numbersNeeded; $i++ ){
                $now             = now();
                $availableNumber = $availableNumbers[$i];
                $purchasedPhone  = null;
                try{
                    $purchasedPhone = $this->numberManager
                                           ->purchase($availableNumber->phoneNumber);
                }catch(Exception $e){
                    Log::error($e->getTraceAsString());
                    continue;
                }

                $inserts[] = [
                    'uuid'                      => Str::uuid(),
                    'phone_number_pool_id'      => $phoneNumberPool->id,
                    'external_id'               => $purchasedPhone->sid,
                    'account_id'                => $company->account_id,
                    'company_id'                => $company->id,
                    'created_by'                => $user->id,
                    'phone_number_config_id'    => $phoneNumberPool->phone_number_config_id,
                    'category'                  => 'ONLINE',
                    'sub_category'              => 'WEBSITE',
                    'type'                      => $type,
                    'country'                   => $company->country,
                    'country_code'              => PhoneNumber::countryCode($purchasedPhone->phoneNumber),
                    'number'                    => PhoneNumber::number($purchasedPhone->phoneNumber),
                    'voice'                     => $purchasedPhone->capabilities['voice'],
                    'sms'                       => $purchasedPhone->capabilities['sms'],
                    'mms'                       => $purchasedPhone->capabilities['mms'],
                    'name'                      => $purchasedPhone->phoneNumber,
                    'source'                    => '-',
                    'medium'                    => null,
                    'content'                   => null,
                    'campaign'                  => null,
                    'swap_rules'                => $request->swap_rules,
                    'purchased_at'              => $now,
                    'created_at'                => $now,
                    'updated_at'                => $now
                ];
            }
        }
        
        try{
            PhoneNumber::insert($inserts);
        }catch(Exception $e){
            DB::rollBack();

            Log::error($e->getTraceAsString());
            
            return response([
                'error' => $e->getMessage()
            ], 500);
        }

        DB::commit();

        return response($phoneNumberPool, 201);
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
            'name' => 'bail|max:64',
            'phone_number_config_id' => [
                'bail',
                (new PhoneNumberConfigRule($company))
            ],
            'override_campaigns'    => 'bail|boolean',
            'swap_rules' => [
                'bail', 
                'json', 
                new SwapRulesRule()
            ],
            'disabled' => 'nullable|boolean'
        ];

        $validator = Validator::make($request->input(), $rules);
        if( $validator->fails() ){
            return response([
                'error' =>  $validator->errors()->first()
            ], 400);
        }

        if( $request->filled('name') )
            $phoneNumberPool->name = $request->name;

        if( $request->filled('phone_number_config_id') )
            $phoneNumberPool->phone_number_config_id = $request->phone_number_config_id;
        
        if( $request->filled('override_campaigns') )
            $phoneNumberPool->override_campaigns = $request->override_campaigns ? true : false;

        if( $request->filled('swap_rules') ){
            $swapRules = json_decode($request->swap_rules);
            $phoneNumberPool->swap_rules = $swapRules;
        }

        if( $request->filled('disabled') )
            $phoneNumberPool->disabled_at = $request->disabled ? ($phoneNumberPool->disabled_at ?: now()) : null;
        
        $phoneNumberPool->save();

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
        //  Remove Pool
        $user         = $request->user();
        $phoneNumbers = $phoneNumberPool->phone_numbers; 

        $phoneNumberPool->delete();
        PhoneNumber::whereIn('id', array_column($phoneNumbers->toArray(),'id'))->delete();

        return response([
            'message' => 'Deleted.'
        ]);
    }

    /**
     * Get list of numbers attached to this pool
     * 
     * @param Request $company
     * @param Company $company
     * @param PhoneNumberPool $phoneNumberPool
     * 
     * @return Response
     */
    public function numbers(Request $request, Company $company, PhoneNumberPool $phoneNumberPool)
    {
         //  Build Query
         $query =PhoneNumber::select([
                                    'phone_numbers.*', 
                                    DB::raw('(SELECT COUNT(*) FROM calls WHERE phone_number_id = phone_numbers.id) AS call_count'),
                                    DB::raw('(SELECT MAX(calls.created_at) FROM calls WHERE phone_number_id = phone_numbers.id) AS last_call_at'),
                                ])
                                ->where('phone_numbers.phone_number_pool_id', $phoneNumberPool->id)
                                ->whereNull('phone_numbers.deleted_at');

            //  Pass along to parent for listing
        return parent::results(
            $request,
            $query,
            [],
            self::$numberFields,
            'phone_numbers.created_at'
        );

        return response($phoneNumberPool->phoneNumbers);
    }

    /**
     * Add phone numbers to pool
     * 
     * @param Request $company
     * @param Company $company
     * @param PhoneNumberPool $phoneNumberPool
     * 
     * @return Response
     */
    public function addNumbers(Request $request, Company $company, PhoneNumberPool $phoneNumberPool)
    {
        $rules = [
            'count'       => 'bail|required|numeric|min:1|max:30',
            'type'        => 'bail|required|in:Local,Toll-Free',
            'starts_with' => 'bail|nullable|digits_between:1,3'
        ];
        $validator = validator($request->input(), $rules);
        if( $validator->fails() ){
            return response([
                'error' => $validator->errors()->first()
            ], 400);
        }

        //  Do not allow users to purchase a number if
        $user       = $request->user(); 
        $account    = $company->account; 
        $startsWith = $request->starts_with;
        $type       = $request->type;
        $count      = intval($request->count);
        if( ! $account->canPurchaseNumbers($count) ){
            return response([
                'error' => 'Unable to purchase ' . $count . ' number(s) for this account - Verify a valid payment method has been added and try again.'
            ], 400);
        }
        
        $bankedNumbers = BankedPhoneNumber::availableNumbers(
            $user->account_id, 
            $company->country, 
            $type, 
            $startsWith, 
            $count
        );

        $totalNumbersFound = count($bankedNumbers);
        $availableNumbers  = [];
        if( $totalNumbersFound < $count ){
            //  Make sure we have enough numbers available
            $availableNumbers = $this->numberManager->listAvailable(
                $startsWith, 
                $count, 
                $type, 
                $company->country
            );
            $totalNumbersFound += count($availableNumbers);
        }
           
        if( $totalNumbersFound < $count ){
            return response([
                'error' => 'Not enough ' . $type . ' numbers available. Try again with a different number type or area code.'
            ], 400);
        }
        
        //
        //  Use banked phone numbers first
        //
        $inserts             = [];
        $purchaseDescription = '';
        foreach( $bankedNumbers as $bankedNumber ){
            //  Add to phone number insert list
            $now       = now();
            $inserts[] = [
                'uuid'                      => Str::uuid(),
                'phone_number_pool_id'      => $phoneNumberPool->id,
                'external_id'               => $bankedNumber->external_id,
                'account_id'                => $company->account_id,
                'company_id'                => $company->id,
                'created_by'                => $user->id,
                'phone_number_config_id'    => $phoneNumberPool->phone_number_config_id,
                'category'                  => 'ONLINE',
                'sub_category'              => 'WEBSITE',
                'type'                      => $bankedNumber->type,
                'country'                   => $bankedNumber->country,
                'country_code'              => $bankedNumber->country_code,
                'number'                    => $bankedNumber->number,
                'voice'                     => $bankedNumber->voice,
                'sms'                       => $bankedNumber->sms,
                'mms'                       => $bankedNumber->mms,
                'name'                      => $bankedNumber->country_code . $bankedNumber->number,
                'source'                    => '-',
                'medium'                    => null,
                'content'                   => null,
                'campaign'                  => null,
                'swap_rules'                => json_encode($phoneNumberPool->swap_rules),
                'purchased_at'              => $bankedNumber->purchased_at,
                'created_at'                => $now,
                'updated_at'                => $now
            ];
        }

        //  Write banked phone numbers
        DB::beginTransaction();

        if( count($bankedNumbers) ){
            $deleteIds = array_column($bankedNumbers->toArray(), 'id');
            try{
                BankedPhoneNumber::whereIn('id', $deleteIds)->delete();
            }catch(Exception $e){
                Log::error($e->getTraceAsString());
                DB::rollBack();
                return response([
                    'error' => 'An error occurred while attempting to purchase numbers - Please try again later.'
                ], 500);
            }
        }
             
        //  See if we need more numbers
        $numbersNeeded = $count - count($inserts); 
        if( $numbersNeeded ){
            for( $i = 0; $i < $numbersNeeded; $i++ ){
                $now             = now();
                $availableNumber = $availableNumbers[$i];
                $purchasedPhone  = null;
                try{
                    $purchasedPhone = $this->numberManager
                                           ->purchase($availableNumber->phoneNumber);
                }catch(Exception $e){
                    Log::error($e->getTraceAsString());
                    continue;
                }

                $inserts[] = [
                    'uuid'                      => Str::uuid(),
                    'phone_number_pool_id'      => $phoneNumberPool->id,
                    'external_id'               => $purchasedPhone->sid,
                    'account_id'                => $company->account_id,
                    'company_id'                => $company->id,
                    'created_by'                => $user->id,
                    'phone_number_config_id'    => $phoneNumberPool->phone_number_config_id,
                    'category'                  => 'ONLINE',
                    'sub_category'              => 'WEBSITE',
                    'type'                      => $type,
                    'country'                   => $company->country,
                    'country_code'              => PhoneNumber::countryCode($purchasedPhone->phoneNumber),
                    'number'                    => PhoneNumber::number($purchasedPhone->phoneNumber),
                    'voice'                     => $purchasedPhone->capabilities['voice'],
                    'sms'                       => $purchasedPhone->capabilities['sms'],
                    'mms'                       => $purchasedPhone->capabilities['mms'],
                    'name'                      => $purchasedPhone->phoneNumber,
                    'source'                    => '-',
                    'medium'                    => null,
                    'content'                   => null,
                    'campaign'                  => null,
                    'swap_rules'                => json_encode($phoneNumberPool->swap_rules),
                    'purchased_at'              => $now,
                    'created_at'                => $now,
                    'updated_at'                => $now
                ];
            }
        }
        
        try{
            //  Add numbers to account
            PhoneNumber::insert($inserts);
        }catch(Exception $e){
            Log::error($e->getTraceAsString());

            DB::rollBack();

            return response([
                'error' => 'An error occurred while attempting to purchase numbers - Please try again later.'
            ], 500);
        }

        DB::commit();

        return response([
            'message' => 'Added.',
            'count'   => count($inserts)
        ], 201);
    }

   /**
     * Attach phone numbers to pool
     * 
     * @param Request $company
     * @param Company $company
     * @param PhoneNumberPool $phoneNumberPool
     * 
     * @return Response
     */
    public function attachNumbers(Request $request, Company $company, PhoneNumberPool $phoneNumberPool)
    {
        $validator = validator($request->input(), [
            'ids' => 'required|json'
        ]);

        if( $validator->fails() )
            return response([
                'error' => $validator->errors()->first()
            ], 400);

        $numberIds = json_decode($request->ids);
        if( ! is_array($numberIds) ){
            return response([
                'error' => 'Numbers must be a json array of phone number ids'
            ], 400);
        }

        $numberIds = array_filter($numberIds, function($numId){
            return is_int($numId);
        });
    
        PhoneNumber::where('company_id', $company->id)
                    ->whereIn('id', $numberIds)
                    ->update([
                        'phone_number_pool_id' => $phoneNumberPool->id,
                        'assignments'          => 0
                    ]);

        return response([
            'message' => 'Attached.',
            'count'   => count($numberIds)
        ]);
    } 

   /**
    * Detach phone numbers from pool
    * 
    * @param Request $company
    * @param Company $company
    * @param PhoneNumberPool $phoneNumberPool
    * 
    * @return Response
    */
    public function detachNumbers(Request $request, Company $company, PhoneNumberPool $phoneNumberPool)
    {
        $validator = validator($request->input(), [
            'ids' => 'required|json'
        ]);

        if( $validator->fails() )
            return response([
                'error' => $validator->errors()->first()
            ], 400);

        $numberIds = json_decode($request->ids);
        if( ! is_array($numberIds) ){
            return response([
                'error' => 'Numbers must be a json array of phone number ids'
            ], 400);
        }

        $numberIds = array_filter($numberIds, function($numId){
            return is_int($numId);
        });
    
        PhoneNumber::where('phone_number_pool_id', $phoneNumberPool->id)
                    ->whereIn('id', $numberIds)
                    ->update([
                        'phone_number_pool_id' => null,
                        'assignments'          => 0
                    ]);

        return response([
            'message' => 'Attached.',
            'count'   => count($numberIds)
        ]);
    }

   /**
    * Delete phone numbers from pool
    * 
    * @param Request $company
    * @param Company $company
    * @param PhoneNumberPool $phoneNumberPool
    * 
    * @return Response
    */
    public function deleteNumbers(Request $request, Company $company, PhoneNumberPool $phoneNumberPool)
    {
        $validator = validator($request->input(), [
            'ids' => 'required|json'
        ]);

        if( $validator->fails() )
            return response([
                'error' => $validator->errors()->first()
            ], 400);

        $numberIds = json_decode($request->ids);
        if( ! is_array($numberIds) ){
            return response([
                'error' => 'Numbers must be a json array of phone number ids'
            ], 400);
        }

        $numberIds = array_filter($numberIds, function($numId){
            return is_int($numId);
        });
    
        $phoneNumbers = PhoneNumber::where('phone_number_pool_id', $phoneNumberPool->id)
                                    ->whereIn('id', $numberIds)
                                    ->get();

        PhoneNumber::where('phone_number_pool_id', $phoneNumberPool->id)
                    ->whereIn('id', $numberIds)
                    ->delete();

        event(new PhoneNumberEvent($company->account, $phoneNumbers, 'delete'));

        return response([ 
            'message' => 'Deleted.',
            'count'   => count($phoneNumbers)
        ]);
    }

    /**
     * Export results
     * 
     */
    public function exportNumbers(Request $request, Company $company, PhoneNumberPool $phoneNumberPool)
    {
        $request->merge([
            'phone_number_pool_id'   => $phoneNumberPool->id,
            'phone_number_pool_name' => $phoneNumberPool->name
        ]);

        return parent::exportResults(
            PhoneNumberPool::class,
            $request,
            [],
            self::$numberFields,
            'phone_numbers.created_at'
        );
    }
}
