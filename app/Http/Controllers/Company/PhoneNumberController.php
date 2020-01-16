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

        $query = PhoneNumber::where('company_id', $company->id);
        
        if( $search ){
            $query->where(function($query) use($search){
                $query->where('name', 'like', '%' . $search . '%');
            });
        }

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
     * Create a phone number
     * 
     */
    public function create(Request $request, Company $company)
    {
        if( ! $company->account->hasValidPaymentMethod() )
            return response([
                'error' => 'You must first add a valid payment method to your account before purchasing a phone number'
            ], 400);

        $rules = [
            'category'            => 'bail|required|in:ONLINE,OFFLINE',
            'source'              => 'bail|required|max:255',        
            'phone_number_config' => [
                'bail',
                'required',
                (new PhoneNumberConfigRule($company))
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
        $validator->sometimes('sub_category', ['bail', 'required', 'in:TV,RADIO,NEWSPAPER,DIRECT_MAIL,FLYER,OTHER'], function($input){
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

        //  Look for a phone number that matches the start_with
        $startsWith   = App::environment(['prod', 'production']) ? $request->starts_with : '';
        $foundNumbers = PhoneNumber::listAvailable($startsWith, 1, $request->toll_free, $company->country) ?: [];
        if( ! count($foundNumbers) )
            return response([
                'error' => 'No phone number could be found for purchase'
            ], 400);

        //  Attempt to purchase phone numbers
        try{
            $purchasedPhone = PhoneNumber::purchase($foundNumbers[0]->phoneNumber);

            $phoneNumber    = PhoneNumber::create([
                'uuid'                      => Str::uuid(),
                'external_id'               => $purchasedPhone->sid,
                'company_id'                => $company->id,
                'created_by'                => $request->user()->id,
                'phone_number_config_id'    => $request->phone_number_config,
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
                'swap_rules'                => json_decode($request->swap_rules) ?: null,
            ]);

            return response($phoneNumber, 201);
        }catch(Exception $e){
            
            return response([
                'error' => $e->getMessage() . 'Unable to purchase phone number'
            ], 400);
        }

        
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
        $rules = [
            'name'                => 'bail|required|max:255',
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
        $validator->sometimes('sub_category', ['bail', 'required', 'in:TV,RADIO,NEWSPAPER,DIRECT_MAIL,FLYER,OTHER'], function($input){
            return $input->category === 'OFFLINE';
        });

        if( $validator->fails() )
            return response([
                'error' => $validator->errors()->first()
            ], 400);

        $phoneNumber->phone_number_config_id    = $request->phone_number_config;
        $phoneNumber->category                  = $request->category;
        $phoneNumber->sub_category              = $request->sub_category;
        $phoneNumber->name                      = $request->name;
        $phoneNumber->source                    = $request->source;
        $phoneNumber->swap_rules                = $request->swap_rules;
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
            'toll_free' => $request->toll_free
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
