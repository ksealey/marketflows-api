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
use App\Models\BankedPhoneNumber;
use App\Models\Company\Call;
use App\Rules\SwapRulesRule;
use App\Rules\Company\BulkPhoneNumberRule;

use App\Helpers\PhoneNumberManager;

use Validator;
use Exception;
use App;
use Log;
use DB;

class PhoneNumberController extends Controller
{
    private $numberManager;

    static $fields = [
        'phone_numbers.name',
        'phone_numbers.number',
        'phone_numbers.disabled_at',
        'phone_numbers.created_at',
        'phone_numbers.updated_at',
        'call_count'
    ];

    public function __construct(PhoneNumberManager $numberManager)
    {
        $this->numberManager = $numberManager;
    }

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
            'medium'              => 'bail|nullable|max:64',
            'content'             => 'bail|nullable|max:64',
            'campaign'            => 'bail|nullable|max:64',       
            'phone_number_config_id' => [
                'bail',
                'required',
                (new PhoneNumberConfigRule($company))
            ],
            'type'          => 'bail|required|in:Toll-Free,Local'
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

        $user    = $request->user(); 
        $account = $company->account; 
        if( ! $account->canPurchaseNumbers(1) ){
            return response([
                'error' => 'Unable to purchase additional numbers for this account - Verify a valid payment method has been added and try again.'
            ], 400);
        }
       
        //  See if we can find an available number in the number bank 
        //  that didn't previously belong to this account
        $startsWith  = $request->type === 'Local' ? $request->starts_with : '';
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
                'account_id'                => $company->account_id,
                'company_id'                => $company->id,
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
                'name'                      => $request->name,
                'source'                    => $request->source,
                'medium'                    => $request->medium ?: null,
                'content'                   => $request->content ?: null,
                'campaign'                  => $request->campaign ?: null,
                'swap_rules'                => ($request->sub_category == 'WEBSITE') ? $request->swap_rules : null,
                'purchased_at'              => $bankedNumber->purchased_at,
                'created_by'                => $user->id,
            ]);

            $bankedNumber->delete();
        }else{
             //  Look for a phone number that matches the start_with
            $foundNumbers = $this->numberManager->listAvailable(
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
                $purchasedPhone = $this->numberManager
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
                'country'                   => $company->country,
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
                'swap_rules'                => ($request->sub_category == 'WEBSITE') ? $request->swap_rules : null,
                'purchased_at'              => now(),
                'created_by'                => $user->id
            ]);
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
            'name'                => 'bail|min:1,max:64',
            'source'              => 'bail|min:1,max:64',  
            'medium'              => 'bail|nullable|max:64',  
            'content'             => 'bail|nullable|max:64',  
            'campaign'            => 'bail|nullable|max:64',        
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

        if( $request->filled('swap_rules') ){
            $phoneNumber->swap_rules = $request->sub_category == 'WEBSITE' ? $request->swap_rules : null;
        }
        
        $phoneNumber->save();

        return response($phoneNumber);
    }

    /**
     * Delete a phone number
     * 
     */
    public function delete(Request $request, Company $company, PhoneNumber $phoneNumber)
    {
        //  Release the number if it will be renewed with 5 days 
        //  or it gets more than 10 calls per day over the last 3 days
        $callsOverThreeDays = $phoneNumber->callsForPreviousDays(3);

        if( $phoneNumber->willRenewInDays(5) || $callsOverThreeDays >= 30 ){
            $this->numberManager
                 ->releaseNumber($phoneNumber);
        }else{
            $this->numberManager
                 ->bankNumber($phoneNumber, $callsOverThreeDays <= 9 ? true : false); // Make avaiable now if it gets less than or equal to 3 calls per day
        }
        
        $phoneNumber->delete();

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

        $bankedNumberCount = $bankedQuery->orderBy('release_by', 'ASC')
                                         ->count();
                                         
        if( $bankedNumberCount >= $request->count ){
            return response([
                'available' => true,
                'count'     => $request->count,
                'type'      => $request->type
            ]);
        }

        $neededCount = $request->count - $bankedNumberCount;
        $numbers     = [];
        try{
            $numbers = $this->numberManager->listAvailable(
                $request->starts_with, 
                $neededCount, 
                $request->type,
                $company->country
            );
        }catch(\Exception $e){}
        
        //  No numbers found
        $totalFound = count($numbers) + $bankedNumberCount;
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
            PhoneNumber::class,
            $request,
            [],
            self::$fields,
            'phone_numbers.created_at'
        );
    }
}
