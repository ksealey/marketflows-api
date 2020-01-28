<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Rules\Company\PhoneNumberPoolRule;
use App\Rules\Company\PhoneNumberConfigRule;
use App\Models\Company;
use App\Models\Company\PhoneNumber;
use App\Rules\SwapRulesRule;
use Validator;
use Exception;
use App;
use Log;

class PhoneNumberController extends Controller
{
    /**
     * List phone numbers
     * 
     */
    public function list(Request $request, Company $company)
    {
        $rules = [
            'limit'     => 'numeric',
            'page'      => 'numeric',
            'order_by'  => 'in:name,number,created_at,updated_at',
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

        $query = PhoneNumber::where('company_id', $company->id)
                            ->whereNull('phone_number_pool_id');
        
        if( $search ){
            $query->where(function($query) use($search){
                $query->where('name', 'like', '%' . $search . '%')
                      ->orWhere('number', 'like', '%' . $search . '%');
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
     * Create a phone number
     * 
     */
    public function create(Request $request, Company $company)
    {
        $rules = [
            'category'            => 'bail|required|in:ONLINE,OFFLINE',
            'source'              => 'bail|required|max:255',        
            'phone_number_config_id' => [
                'bail',
                'required',
                (new PhoneNumberConfigRule($company))
            ],
            'phone_number_pool_id' => [
                'bail',
                (new PhoneNumberPoolRule($company))
            ],
            'name'          => 'bail|max:255',
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
        
        if( ! $account->canPurchase($purchaseObject, 1, true) )
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
                'created_by'                => $user->id,
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
                'swap_rules'                => ($request->sub_category == 'WEBSITE') ? json_decode($request->swap_rules) : null
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

            return response([
                'error' => 'Unable to purchase phone number - Please try again later.'
            ], 400);
        }

        return response($phoneNumber, 201);
    }

    /**
     * Read a phone number
     * 
     */
    public function read(Request $request, Company $company, PhoneNumber $phoneNumber)
    {
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

        //  Do not allow
        $rules = [
            'source'              => 'bail|max:255',        
            'phone_number_config' => [
                'bail',
                (new PhoneNumberConfigRule($company))
            ],
            'name' => 'bail|max:255'
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

        if( $request->filled('phone_number_config') )
            $phoneNumber->phone_number_config_id = $request->phone_number_config_id;
        if( $request->filled('category') )
            $phoneNumber->category = $request->category;
        if( $request->filled('sub_category') )
            $phoneNumber->sub_category = $request->sub_category;
        if( $request->filled('name') )
            $phoneNumber->name = $request->name;
        if( $request->filled('source') )
            $phoneNumber->source = $request->source;
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
