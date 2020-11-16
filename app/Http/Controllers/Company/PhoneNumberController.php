<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Rules\Company\PhoneNumberConfigRule;
use App\Models\Company;
use App\Models\Transaction;
use App\Models\Company\PhoneNumber;
use App\Models\Company\Call;
use App\Rules\SwapRulesRule;
use App\Rules\Company\BulkPhoneNumberRule;
use Validator;
use Exception;
use App;
use Log;
use DB;

class PhoneNumberController extends Controller
{
    static $fields = [
        'phone_numbers.id',
        'phone_numbers.name',
        'phone_numbers.country_code',
        'phone_numbers.number',
        'phone_numbers.type',
        'phone_numbers.category',
        'phone_numbers.sub_category',
        'phone_numbers.source',
        'phone_numbers.medium',
        'phone_numbers.campaign',
        'phone_numbers.content',
        'phone_numbers.company_id',
        'phone_numbers.disabled_at',
        'phone_numbers.created_at',
        'phone_numbers.updated_at',
        'phone_numbers.last_call_at',
        'phone_numbers.status',
        'phone_numbers.is_paid',
        'phone_numbers.is_organic',
        'phone_numbers.is_referral',
        'phone_numbers.is_search',
        'phone_number_call_count.call_count'
    ];

    /**
     * List phone numbers
     * 
     */
    public function list(Request $request, Company $company)
    {
        //  Build Query
        $query = PhoneNumber::exportQuery($request->user(), ['company_id' => $company->id]);
                    
        //  Pass along to parent for listing
        return parent::results(
            $request,
            $query,
            [],
            self::$fields,
            'phone_numbers.created_at'
        );
    }

    /**
     * Create a phone number
     * 
     */
    public function create(Request $request, Company $company)
    {
        $user    = $request->user(); 
        $account = $company->account;

        //  Block suspended accounts
        if( $account->suspended_at ){
            return response([
                'error' => 'Your account is suspended. Please resolve outstanding issues and try again.'
            ], 403);
        }

        //  Block accounts from purchasing more that 5 numbers when they have no payment method added
        if( ! $account->hasValidPaymentMethod() ){
            if( $account->phoneNumberCount() >= 10 ){
                return response([
                    'error' => 'You have reached your limit of phone numbers. Please add a valid payment method and try again.'
                ], 403);
            }
        }
        
        $rules = [
            'name'                => 'bail|required|max:64',
            'category'            => 'bail|required|in:ONLINE,OFFLINE',
            'source'              => 'bail|required|max:64', 
            'medium'              => 'bail|nullable|max:64',
            'content'             => 'bail|nullable|max:64',
            'campaign'            => 'bail|nullable|max:64',   
            'is_paid'             => 'bail|nullable|boolean', 
            'is_organic'          => 'bail|nullable|boolean',  
            'is_direct'           => 'bail|nullable|boolean', 
            'is_referral'         => 'bail|nullable|boolean',   
            'is_search'           => 'bail|nullable|boolean',      
            'phone_number_config_id' => [
                'bail',
                'required',
                (new PhoneNumberConfigRule($company))
            ],
            'type'          => 'bail|required|in:Toll-Free,Local',
            
        ];

        $validator = validator($request->input(), $rules);

        //  Make sure the sub_category is valid for the category
        $validator->sometimes('sub_category', ['bail', 'required', 'in:WEBSITE,SOCIAL_MEDIA,EMAIL'], function($input){
            return $input->category === 'ONLINE';
        });
        $validator->sometimes('sub_category', ['bail', 'required', 'in:TV,RADIO,NEWSPAPER,DIRECT_MAIL,FLYER,BILLBOARD,OTHER'], function($input){
            return $input->category === 'OFFLINE';
        });
        $validator->sometimes('swap_rules', ['bail', 'required', 'json', new SwapRulesRule()], function($input){
            return $input->sub_category == 'WEBSITE';
        });
        $validator->sometimes('starts_with', ['bail', 'required', 'digits_between:1,10'], function($input){
            return $input->type === 'Local';
        });

        if( $validator->fails() )
            return response([
                'error' => $validator->errors()->first()
            ], 400);
       
        
        $startsWith   = $request->type === 'Local' ? $request->starts_with : '';
        $foundNumbers = $this->numberService->listAvailable(
            $startsWith, 
            1, 
            $request->type, 
            $company->country
        ) ?: [];

        if( ! count($foundNumbers) )
            return response([
                'error' => 'No phone number could be found for purchase'
            ], 400);

        try{
            $purchasedPhone = $this->numberService
                                   ->purchase($foundNumbers[0]->phoneNumber);
        }catch(Exception $e){
            Log::error($e->getTraceAsString());

            return response([
                'error' => 'Unable to purchase number - Please try again later.'
            ], 400);
        }

        $phoneNumber = PhoneNumber::create([
            'uuid'                      => Str::uuid(),
            'external_id'               => $purchasedPhone->sid,
            'account_id'                => $company->account_id,
            'company_id'                => $company->id,
            'phone_number_config_id'    => $request->phone_number_config_id,
            'category'                  => $request->category,
            'sub_category'              => $request->sub_category,
            'type'                      => $request->type,
            'country_code'              => PhoneNumber::countryCode($purchasedPhone->phoneNumber),
            'number'                    => PhoneNumber::number($purchasedPhone->phoneNumber),
            'voice'                     => $purchasedPhone->capabilities['voice'],
            'sms'                       => $purchasedPhone->capabilities['sms'],
            'mms'                       => $purchasedPhone->capabilities['mms'],
            'name'                      => $request->name,
            'source'                    => $request->source,
            'medium'                    => $request->medium,
            'content'                   => $request->content,
            'campaign'                  => $request->campaign,
            'is_paid'                   => boolval($request->is_paid),
            'is_organic'                => boolval($request->is_organic),
            'is_direct'                 => boolval($request->is_direct),
            'is_referral'               => boolval($request->is_referral),
            'is_search'                 => boolval($request->is_search),
            'swap_rules'                => ($request->sub_category == 'WEBSITE') ? $request->swap_rules : null,
            'purchased_at'              => now(),
            'created_by'                => $user->id
        ]);

        $phoneNumber->call_count = 0;

        return response($phoneNumber, 201);
    }

    /**
     * Read a phone number
     * 
     */
    public function read(Request $request, Company $company, PhoneNumber $phoneNumber)
    {
        $phoneNumber->call_count = $phoneNumber->call_count;

        return response($phoneNumber);
    }

    /**
     * Update a phone number
     * 
     */
    public function update(Request $request, Company $company, PhoneNumber $phoneNumber)
    {
        if( $phoneNumber->keyword_tracking_pool_id ){
            return response([
                'error' => 'This number belongs to a keyword tracking pool and cannot be updated'
            ], 400);
        }

        $rules = [
            'disabled'            => 'bail|boolean',
            'name'                => 'bail|min:1,max:64',
            'source'              => 'bail|min:1,max:64',  
            'medium'              => 'bail|nullable|max:64',  
            'content'             => 'bail|nullable|max:64',  
            'campaign'            => 'bail|nullable|max:64', 
            'is_paid'             => 'bail|nullable|boolean', 
            'is_organic'          => 'bail|nullable|boolean',  
            'is_direct'           => 'bail|nullable|boolean', 
            'is_referral'         => 'bail|nullable|boolean',   
            'is_search'           => 'bail|nullable|boolean',           
            'phone_number_config' => [
                'bail',
                (new PhoneNumberConfigRule($company))
            ]
        ];

        $validator = validator($request->input(), $rules);

        //  Make sure the sub_category is valid for the category
        $validator->sometimes('sub_category', ['bail', 'required', 'in:WEBSITE,SOCIAL_MEDIA,EMAIL'], function($input){
            return $input->category === 'ONLINE';
        });
        $validator->sometimes('sub_category', ['bail', 'required', 'in:TV,RADIO,NEWSPAPER,DIRECT_MAIL,FLYER,BILLBOARD,OTHER'], function($input){
            return $input->category === 'OFFLINE';
        });

        //  Make sure the swap rules are there and valid when it's for a website
        $validator->sometimes('swap_rules', ['bail', 'required', 'json', new SwapRulesRule()], function($input){
            return $input->sub_category == 'WEBSITE';
        });

        if( $validator->fails() )
            return response([
                'error' => $validator->errors()->first()
            ], 400);

        if( $request->filled('disabled') )
            $phoneNumber->disabled_at = $request->disabled ? ($phoneNumber->disabled_at ?: now()) : null;
        if( $request->filled('phone_number_config_id') )
            $phoneNumber->phone_number_config_id = $request->phone_number_config_id;
        if( $request->filled('category') )
            $phoneNumber->category = $request->category;
        if( $request->filled('sub_category') )
            $phoneNumber->sub_category = $request->sub_category;
        if( $request->filled('name') )
            $phoneNumber->name = $request->name;
        if( $request->filled('source') )
            $phoneNumber->source = $request->source;
        if( $request->has('medium') )
            $phoneNumber->medium = $request->medium ?: null;
        if( $request->has('content') )
            $phoneNumber->content = $request->content ?: null;
        if( $request->has('campaign') )
            $phoneNumber->campaign = $request->campaign ?: null;
        if( $request->filled('is_paid') )
            $phoneNumber->is_paid = boolval($request->is_paid);
        if( $request->filled('is_organic') )
            $phoneNumber->is_organic = boolval($request->is_organic);
        if( $request->filled('is_direct') )
            $phoneNumber->is_direct = boolval($request->is_direct);
        if( $request->filled('is_referral') )
            $phoneNumber->is_referral = boolval($request->is_referral);
        if( $request->filled('is_search') )
            $phoneNumber->is_search = boolval($request->is_search);

        if( $request->filled('swap_rules') ){
            $phoneNumber->swap_rules = $request->sub_category == 'WEBSITE' ? $request->swap_rules : null;
        }
        
        $phoneNumber->save();

        $phoneNumber->call_count = $phoneNumber->call_count;

        return response($phoneNumber);
    }

    /**
     * Delete a phone number
     * 
     */
    public function delete(Request $request, Company $company, PhoneNumber $phoneNumber)
    {
        if( $phoneNumber->keyword_tracking_pool_id ){
            return response([
                'error' => 'This number belongs to a keyword tracking pool and cannot be deleted'
            ], 400);
        }

        $this->numberService
            ->releaseNumber($phoneNumber);
        
        $phoneNumber->deleted_by = $request->user()->id;
        $phoneNumber->deleted_at = now();
        $phoneNumber->save();

        return response([
            'message' => 'Deleted'
        ]);
    }
    
    /**
     * Check that phone numbers are available for the provided area codes
     * 
     */
    public function checkNumbersAvailable(Request $request, Company $company)
    {
        $rules = [
            'type'          => 'required|in:Local,Toll-Free',
            'count'         => 'required|numeric|min:1|max:30',
            'starts_with'   => 'digits_between:1,10'
        ];

        $validator = Validator::make($request->input(), $rules);
        if( $validator->fails() ){
            return response([
                'error' => $validator->errors()->first()
            ], 400);
        }

        try{
            $numbers = $this->numberService->listAvailable(
                $request->starts_with, 
                $request->count, 
                $request->type,
                $company->country
            );
        }catch(\Exception $e){}
        
        //  No numbers found
        $numberCount = count($numbers);
        if( $numberCount=== 0 ){
            return response([
                'available' => false,
                'error'     => 'No numbers found - Please try again with a different search.',
                'count'     => 0,
                'type'      => $request->type
            ], 400);
        }

        //  Not enough numbers found
        if( $numberCount < $request->count ){
            return response([
                'available' => false,
                'error'     => 'Not enough numbers found(' . $numberCount . ') for purchase - Please try again with a different search.',
                'count'     => $numberCount,
                'type'      => $request->type
            ], 400);
        }

        return response([
            'available' => true,
            'count'     => $numberCount,
            'type'      => $request->type
        ], 200);
    }

    /**
     * Export results
     * 
     */
    public function export(Request $request, Company $company)
    {
        $request->merge([
            'company_id'   => $company->id,
            'company_name' => $company->name
        ]);
        
        return parent::exportResults(
            PhoneNumber::class,
            $request,
            [],
            self::$fields,
            'phone_numbers.created_at'
        );
    }
}
