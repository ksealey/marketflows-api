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
use App\Models\Company\BankedPhoneNumber;
use App\Models\Company\Call;
use App\Rules\SwapRulesRule;
use App\Rules\Company\BulkPhoneNumberRule;

use App\Events\Company\PhoneNumberEvent;

use Validator;
use Exception;
use App;
use Log;
use DB;

class PhoneNumberController extends Controller
{
    static $fields = [
        'phone_numbers.name',
        'phone_numbers.number',
        'phone_numbers.disabled_at',
        'phone_numbers.created_at',
        'phone_numbers.updated_at',
        'call_count'
    ];

    /**
     * List phone numbers
     * 
     */
    public function list(Request $request, Company $company)
    {
        //  Build Query
        $query = PhoneNumber::select([
                        'phone_numbers.*', 
                        DB::raw('(SELECT COUNT(*) FROM calls WHERE phone_number_id = phone_numbers.id) AS call_count'),
                        DB::raw('(SELECT MAX(calls.created_at) FROM calls WHERE phone_number_id = phone_numbers.id) AS last_call_at'),
                    ])
                    ->whereNull('phone_numbers.phone_number_pool_id')
                    ->whereNull('phone_numbers.deleted_at')
                    ->where('phone_numbers.company_id', $company->id);

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
        $rules = [
            'name'                => 'bail|required|max:64',
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
            'type'          => 'bail|required|in:Toll-Free,Local',
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
        $validator->sometimes('swap_rules', ['bail', 'required', 'json', new SwapRulesRule()], function($input){
            return $input->sub_category == 'WEBSITE';
        });

        if( $validator->fails() )
            return response([
                'error' => $validator->errors()->first()
            ], 400);

        //  Make sure that account balance can purchase object
        $purchaseObject = 'PhoneNumber.' . $request->type; 
        $user           = $request->user(); 
        $account        = $company->account; 
        
        if( ! $account->balanceCovers($purchaseObject, 1, true) )
            return response([
                'error' => 'Your account balance(' . $account->rounded_balance  . ') is too low to complete purchase. Reload account balance or turn on auto-reload in your account payment settings and try again.'
            ], 400);

         //  See if we can find an available number in the number bank 
         //  that didn't previously belong to this account
        $startsWith  = $request->starts_with;
        $bankedQuery = BankedPhoneNumber::where('status', 'Available')
                                        ->where('released_by_account_id', '!=', $account->id)
                                        ->where('country', $company->country)
                                        ->where('type', $request->type);
        
        if( $startsWith )
             $bankedQuery->where('number', 'like', $startsWith . '%');

        $bankedNumber = $bankedQuery->orderBy('release_by', 'ASC')
                                    ->first();

        if( $bankedNumber ){
            $phoneNumber = PhoneNumber::create([
                'uuid'                      => Str::uuid(),
                'external_id'               => $bankedNumber->external_id,
                'company_id'                => $company->id,
                'user_id'                   => $user->id,
                'phone_number_config_id'    => $request->phone_number_config_id,
                'category'                  => $request->category,
                'sub_category'              => $request->sub_category,
                'type'                      => $request->type,
                'country'                   => $bankedNumber->country,
                'country_code'              => $bankedNumber->country_code,
                'number'                    => $bankedNumber->number,
                'voice'                     => $bankedNumber->voice,
                'sms'                       => $bankedNumber->sms,
                'mms'                       => $bankedNumber->mms,
                'name'                      => $request->name ?: '+' . $bankedNumber->country_code . $bankedNumber->number,
                'source'                    => $request->source,
                'medium'                    => $request->medium,
                'content'                   => $request->content,
                'campaign'                  => $request->campaign,
                'swap_rules'                => ($request->sub_category == 'WEBSITE') ? json_decode($request->swap_rules) : null,
                'purchased_at'              => $bankedNumber->purchased_at
            ]);
        }else{
             //  Look for a phone number that matches the start_with
            $foundNumbers = PhoneNumber::listAvailable(
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
                $purchasedPhone = PhoneNumber::purchase($foundNumbers[0]->phoneNumber);
            }catch(Exception $e){
                Log::error($e->getTraceAsString());
                
                return response([
                    'error' => 'Unable to purchase number - Please try again later.'
                ], 400);
            }

            $phoneNumber = PhoneNumber::create([
                'uuid'                      => Str::uuid(),
                'external_id'               => $purchasedPhone->sid,
                'company_id'                => $company->id,
                'user_id'                   => $user->id,
                'phone_number_config_id'    => $request->phone_number_config_id,
                'category'                  => $request->category,
                'sub_category'              => $request->sub_category,
                'type'                      => $request->type,
                'country'                   => $company->country,
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
                'swap_rules'                => ($request->sub_category == 'WEBSITE') ? json_decode($request->swap_rules) : null,
                'purchased_at'              => now()
            ]);
        }

        $phoneNumber->call_count = 0;
        
        event(new PhoneNumberEvent($user, [$phoneNumber], 'create'));

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

        event(new PhoneNumberEvent($request->user(), [$phoneNumber], 'update'));

        return response($phoneNumber);
    }

    /**
     * Delete a phone number
     * 
     */
    public function delete(Request $request, Company $company, PhoneNumber $phoneNumber)
    {
        $phoneNumber->delete();

        event(new PhoneNumberEvent($request->user(), [$phoneNumber], 'delete'));
        
        return response([
            'message' => 'deleted'
        ]);
    }

     /**
     * Bulk Delete
     * 
     */
    public function bulkDelete(Request $request, Company $company)
    {
        $user = $request->user();

        $validator = validator($request->input(), [
            'ids' => ['required','json']
        ]);

        if( $validator->fails() ){
            return response([
                'error' => $validator->errors()->first()
            ], 400);
        }

        $phoneNumberIds = array_values(json_decode($request->ids, true) ?: []);
        $phoneNumberIds = array_filter($phoneNumberIds, function($item){
            return is_string($item) || is_numeric($item);
        });
        $phoneNumbers = PhoneNumber::whereIn('id', $phoneNumberIds)
                                   ->whereIn('company_id', function($query) use($user){
                                         $query->select('company_id')
                                               ->from('user_companies')
                                               ->where('user_id', $user->id);
                                    })
                                    ->get()
                                    ->toArray();

        $phoneNumberIds = array_column($phoneNumbers, 'id');
        if( count($phoneNumbers) ){
            PhoneNumber::whereIn('id', $phoneNumberIds)->delete();

            event(new PhoneNumberEvent($user, $phoneNumbers, 'delete'));
        }

        return response([
            'message' => 'Deleted.'
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

        $user        = $request->user();
        $bankedQuery = BankedPhoneNumber::where('status', 'Available')
                                        ->where('released_by_account_id', '!=', $user->account_id)
                                        ->where('country', $company->country)
                                        ->where('type', $request->type);

        if( $request->starts_with )
            $bankedQuery->where('number', 'like', $request->starts_with . '%');

        $bankedNumbers = $bankedQuery->orderBy('release_by', 'ASC')
                                   ->limit($request->count)
                                   ->get();
        $bankedCount   = count($bankedNumbers);

        if( $bankedCount == $request->count ){
            return response([
                'available' => true,
                'count'     => $bankedCount,
                'type'      => $request->type
            ]);
        }

        $neededCount = $request->count - $bankedCount;
        $numbers     = [];
        try{
            $numbers = PhoneNumber::listAvailable(
                $request->starts_with, 
                $neededCount, 
                $request->type,
                $company->country
            );
        }catch(\Exception $e){}
        
        //  No numbers found
        $totalFound = count($numbers) + $bankedCount;
        if( $totalFound === 0 ){
            return response([
                'available' => false,
                'error'     => 'No numbers found - Please try again with a different search.',
                'count'     => $totalFound,
                'type'      => $request->type
            ], 400);
        }

        //  Not enough numbers found
        if( $totalFound < $request->count ){
            return response([
                'available' => false,
                'error'     => 'Not enough numbers found(' . $totalFound. ') for purchase - Please try again with a different search.',
                'count'     => $totalFound,
                'type'      => $request->type
            ], 400);
        }

        return response([
            'available' => true,
            'count'     => $totalFound,
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
            PhoneNumberPool::class,
            $request,
            [],
            self::$fields,
            'phone_numbers.created_at'
        );
    }
}
