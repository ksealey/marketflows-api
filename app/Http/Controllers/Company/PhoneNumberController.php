<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Rules\Company\PhoneNumberPoolRule;
use App\Rules\Company\PhoneNumberConfigRule;
use App\Models\Company;
use App\Models\Transaction;
use App\Models\Company\PhoneNumber;
use App\Models\Company\Call;
use App\Rules\SwapRulesRule;
use App\Rules\DateFilterRule;
use Validator;
use Exception;
use App;
use Log;
use DB;

class PhoneNumberController extends Controller
{
    /**
     * List phone numbers
     * 
     */
    public function list(Request $request, Company $company)
    {
        //  Set additional rules
        $rules = [
            'order_by' => 'in:phone_numbers.name,phone_numbers.number,phone_numbers.category,phone_numbers.sub_category,phone_numbers.disabled_at,phone_numbers.created_at,phone_numbers.updated_at'
        ];

        //  Build Query
        $query = DB::table('phone_numbers')
                    ->select(['phone_numbers.*', DB::raw('(SELECT COUNT(*) FROM calls WHERE phone_number_id = phone_numbers.id) AS call_count')])
                    ->whereNull('phone_numbers.phone_number_pool_id')
                    ->whereNull('phone_numbers.deleted_at')
                    ->where('phone_numbers.company_id', $company->id);

        $searchFields = [
            'phone_numbers.name',
            'phone_numbers.number',
            'phone_numbers.category',
            'phone_numbers.sub_category'
        ];

        //  Pass along to parent for listing
        return parent::results(
            $request,
            $query,
            $rules,
            $searchFields,
            'phone_numbers.created_at'
        );
    }

    /**
     * Create a phone number
     * 
     */
    public function create(Request $request, Company $company)
    {
        $rules = [
            'category'            => 'bail|required|in:ONLINE,OFFLINE',
            'source'              => 'bail|required|max:64', 
            'medium'              => 'bail|max:64',
            'content'             => 'bail|max:64',
            'campaign'            => 'bail|max:64',       
            'phone_number_config_id' => [
                'bail',
                'required',
                (new PhoneNumberConfigRule($company))
            ],
            'phone_number_pool_id' => [
                'bail',
                (new PhoneNumberPoolRule($company))
            ],
            'name'          => 'bail|max:64',
            'toll_free'     => 'bail|boolean',
            'starts_with'   => 'bail|digits_between:1,10'
        ];

        $validator = Validator::make($request->input(), $rules);

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

        //  Make sure that account balance can purchase object
        $purchaseObject = 'PhoneNumber.' . ($request->toll_free ? 'TollFree' : 'Local'); 
        $user           = $request->user(); 
        $account        = $company->account; 
        
        if( ! $account->balanceCovers($purchaseObject, 1, true) )
            return response([
                'error' => 'Your account balance(' . $account->rounded_balance  . ') is too low to complete purchase. Reload account balance or turn on auto-reload in your account payment settings and try again.'
            ], 400);

        //  Look for a phone number that matches the start_with
        $startsWith   = $request->starts_with;
        $foundNumbers = PhoneNumber::listAvailable($startsWith, 1, $request->toll_free, $company->country) ?: [];
        if( ! count($foundNumbers) )
            return response([
                'error' => 'No phone number could be found for purchase'
            ], 400);

        //  Attempt to purchase phone numbers
        try{
            $purchasedPhone = PhoneNumber::purchase($foundNumbers[0]->phoneNumber);

            $phoneNumber = PhoneNumber::create([
                'uuid'                      => Str::uuid(),
                'external_id'               => $purchasedPhone->sid,
                'company_id'                => $company->id,
                'user_id'                   => $user->id,
                'phone_number_config_id'    => $request->phone_number_config_id,
                'phone_number_pool_id'      => $request->phone_number_pool_id,
                'category'                  => $request->category,
                'sub_category'              => $request->sub_category,
                'toll_free'                 => $request->toll_free ? 1 : 0,
                'country_code'              => PhoneNumber::countryCode($purchasedPhone->phoneNumber),
                'number'                    => PhoneNumber::number($purchasedPhone->phoneNumber),
                'voice'                     => $purchasedPhone->capabilities['voice'],
                'sms'                       => $purchasedPhone->capabilities['sms'],
                'mms'                       => $purchasedPhone->capabilities['mms'],
                'name'                      => $request->name ?: $purchasedPhone->phoneNumber,
                'source'                    => $request->source,
                'medium'                    => $request->medium,
                'content'                   => $request->content,
                'campaign'                  => $request->campaign,
                'swap_rules'                => ($request->sub_category == 'WEBSITE') ? json_decode($request->swap_rules) : null
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
            
            return response([
                'error' => $e->getMessage()
            ], 400);
        }

        $phoneNumber->call_count = 0;

        return response($phoneNumber, 201);
    }

    /**
     * Read a phone number
     * 
     */
    public function read(Request $request, Company $company, PhoneNumber $phoneNumber)
    {
        $phoneNumber->call_count = Call::where('phone_number_id', $phoneNumber->id)->count();

        return response($phoneNumber);
    }

    /**
     * Update a phone number
     * 
     */
    public function update(Request $request, Company $company, PhoneNumber $phoneNumber)
    {
        //  Disable updates when attached to a pool
        if( $phoneNumber->phone_number_pool_id ){
            return response([
                'error' => 'This phone number is associated with a phone number pool. You must detach it before attempting to update.'
            ], 400);
        }

        $rules = [
            'disabled'            => 'bail|boolean',
            'name'                => 'bail|max:64',
            'source'              => 'bail|max:64',  
            'medium'              => 'bail|max:64',  
            'content'             => 'bail|max:64',  
            'campaign'            => 'bail|max:64',        
            'phone_number_config' => [
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
        if( $request->filled('medium') )
            $phoneNumber->medium = $request->medium;
        if( $request->filled('content') )
            $phoneNumber->content = $request->content;
        if( $request->filled('campaign') )
            $phoneNumber->campaign = $request->campaign;

        if( $request->filled('swap_rules') )
            $phoneNumber->swap_rules = $request->sub_category == 'WEBSITE' ? json_decode($request->swap_rules) : null;
        
        $phoneNumber->save();

        return response($phoneNumber);
    }

    /**
     * Delete a phone number
     * 
     */
    public function delete(Request $request, Company $company, PhoneNumber $phoneNumber)
    {
        if( $phoneNumber->isInUse() ){
            return response([
                'error' => 'This phone number is in use - please detach from all related entities and try again'
            ], 400);
        }

        $phoneNumber->release();

        return response([
            'message' => 'deleted'
        ]);
    }

    /**
     * Attach a phone number to a phone number pool
     * 
     */
    public function attach(Request $request, Company $company, PhoneNumber $phoneNumber)
    {
        $rules = [
            'phone_number_pool_id' => [
                'bail',
                'required',
                new PhoneNumberPoolRule($company)
            ]
        ];

        $validator = Validator::make($request->input(), $rules);
        if( $validator->fails() )
            return response([
                'error' => $validator->errors()->first()
            ], 400);

        $phoneNumber->phone_number_pool_id = $request->phone_number_pool_id;
        $phoneNumber->save();

        return response($phoneNumber);
    }

    /**
     * Detach a phone number from a phone number pool
     * 
     */
    public function detach(Request $request, Company $company, PhoneNumber $phoneNumber)
    {
        $rules = [
            'category'            => 'bail|required|in:ONLINE,OFFLINE',
            'source'              => 'bail|required|max:255',        
            'phone_number_config' => [
                'bail',
                'required',
                (new PhoneNumberConfigRule($company))
            ]
        ];

        $validator = Validator::make($request->input(), $rules);

        //  Make sure the sub_category is valid for the category
        $validator->sometimes('sub_category', ['bail', 'required', 'in:WEBSITE,SOCIAL_MEDIA,EMAIL'], function($input){
            return $input->category === 'ONLINE';
        });
        $validator->sometimes('sub_category', ['bail', 'required', 'in:TV,RADIO,NEWSPAPER,DIRECT_MAIL,FLYER,BILLOARD,OTHER'], function($input){
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
    
        $phoneNumber->phone_number_pool_id      = null;
        $phoneNumber->phone_number_config_id    = $request->phone_number_config_id;
        $phoneNumber->category                  = $request->category;
        $phoneNumber->sub_category              = $request->sub_category;
        $phoneNumber->name                      = $request->name;
        $phoneNumber->source                    = $request->source;
        $phoneNumber->swap_rules                = $request->sub_category == 'WEBSITE' ? json_decode($request->swap_rules) : null;
        
        $phoneNumber->save();

        return response($phoneNumber);
    }

    /**
     * Check that phone numbers are available for the provided area codes
     * 
     */
    public function checkNumbersAvailable(Request $request, Company $company)
    {
        $rules = [
            'toll_free'     => 'required|boolean',
            'count'         => 'required|digits_between:1,2',
            'starts_with'   => 'digits_between:1,10'
        ];

        $validator = Validator::make($request->input(), $rules);
        if( $validator->fails() ){
            return response([
                'error' => $validator->errors()->first()
            ], 400);
        }

        $numbers = [];
        try{
            $numbers = PhoneNumber::listAvailable(
                $request->starts_with, 
                $request->count, 
                $request->toll_free,
                $company->country
            );
        }catch(\Exception $e){}

        $response = [
            'available' => count($numbers) ? true : false,
            'count'     => count($numbers),
            'toll_free' => boolval($request->toll_free)
        ];
        $statusCode = 200;

        if( ! count($numbers) ){
            $error = 'No phone numbers could be found for this company\'s country(' 
                                . $company->country 
                                . ')';
            if( $request->starts_with )
                $error .= ' starting with ' . $request->starts_with;

            $response['error'] = $error;

            $statusCode = 400;
        }

        return response($response, $statusCode);
    }
}
