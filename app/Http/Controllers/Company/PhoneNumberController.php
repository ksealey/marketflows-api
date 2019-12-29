<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Rules\Company\PhoneNumberPoolRule;
use App\Rules\Company\PhoneNumberConfigRule;
use App\Models\Company;
use App\Models\Company\PhoneNumber;
use Validator;
use Exception;

class PhoneNumberController extends Controller
{
    /**
     * List phone numbers
     * 
     */
    public function list(Request $request, Company $company)
    {
        $limit  = intval($request->limit) ?: 25;
        $page   = intval($request->page) ? intval($request->page) - 1 : 0;
        $search = $request->search;

        $query = PhoneNumber::where('company_id', $company->id);
        
        if( $search ){
            $query->where(function($query) use($search){
                $query->where('name', 'like', '%' . $search . '%');
            });
        }

        $resultCount = $query->count();
        $records     = $query->offset($page * $limit)
                             ->limit($limit)
                             ->get();

        return response([
            'results'       => $records,
            'result_count'  => $resultCount,
            'limit'         => $limit,
            'page'          => $page + 1,
            'total_pages'   => ceil($resultCount / $limit)
        ]);
    }

    /**
     * Create a phone number
     * 
     */
    public function create(Request $request, Company $company)
    {
        $config = config('services.twilio');
        $rules = [
            'name'                => 'bail|required|max:255',
            'area_code'           => 'bail|required|digits_between:1,3',
            'category'            => 'bail|required|in:Online,Offline',
            'sub_category'        => 'bail|required|max:255',        
            'phone_number_config' => [
                'bail',
                'required',
                (new PhoneNumberConfigRule($company))
            ]
        ];

        $validator = Validator::make($request->input(), $rules);
        if( $validator->fails() )
            return response([
                'error' => $validator->errors()->first()
            ], 400);

        try{
            $availableNumbers = PhoneNumber::listAvailable($request->area_code ?: null);
            return response([
                'error' => json_encode($availableNumbers)
            ], 400);
            
            if( ! count($availableNumbers) )
                return response([
                    'error' => 'No available phone numbers found for area code ' . $request->area_code
                ], 400);
            
            /*$purchasedPhone = PhoneNumber::purchaseWithAreaCode($request->area_code);

            $phoneNumber = PhoneNumber::create([
                'uuid'                      => Str::uuid(),
                'company_id'                => $company->id,
                'created_by'                => $user->id,
                'external_id'               => $purchasedPhone->sid,
                'country_code'              => PhoneNumber::countryCode($purchasedPhone->phoneNumber),
                'number'                    => PhoneNumber::number($purchasedPhone->phoneNumber),
                'voice'                     => $purchasedPhone->capabilities['voice'],
                'sms'                       => $purchasedPhone->capabilities['sms'],
                'mms'                       => $purchasedPhone->capabilities['mms'],
                'name'                      => $request->name ?: $purchasedPhone->phoneNumber,
            ]);*/
        }catch(Exception $e){
            return response([
                'error' => $e->getMessage()//'Unable to purchase a phone number for the provided area code'
            ], 400);
        }

        return response([
            'phone_number' => $phoneNumber
        ], 201);
    }

    /**
     * Read a phone number
     * 
     */
    public function read(Request $request, Company $company, PhoneNumber $phoneNumber)
    {
        return response([
            'phone_number' => $phoneNumber
        ]);
    }

    /**
     * Update a phone number
     * 
     */
    public function update(Request $request, Company $company, PhoneNumber $phoneNumber)
    {
        $config = config('services.twilio');
        
        $rules = [
            'phone_number_pool' => ['bail', new PhoneNumberPoolRule($company)],
            'phone_number_config'=>['bail', 'required', 'numeric', new PhoneNumberConfigRule($company)],
            'name'              => 'bail|required|max:255',
        ];

        $validator = Validator::make($request->input(), $rules);
        if( $validator->fails() ){
            return response([
                'error' => $validator->errors()->first()
            ], 400);
        }

        $phoneNumber->name                      = $request->name;
        $phoneNumber->phone_number_pool_id      = $request->phone_number_pool;
        $phoneNumber->phone_number_config_id    = $request->phone_number_config;
        $phoneNumber->save();

        return response([
            'phone_number' => $phoneNumber
        ]);
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
    public function checkLocalNumbersAvailable(Request $request, Company $company)
    {
        $rules = [
            'area_code'   => 'required|digits_between:1,3',
            'count'       => 'digits_between:1,2'        
        ];

        $validator = Validator::make($request->input(), $rules);
        if( $validator->fails() ){
            return response([
                'error' => $validator->errors()->first()
            ], 400);
        }

        $count   = intval($request->count) ?: 1;

        $notFoundMessage = 'No phone numbers could be found for this company\'s country(' 
                            . $company->country 
                            . ') and area code(' 
                            . $request->area_code
                            . ')';
        try{
            $numbers = PhoneNumber::listAvailableLocal($request->area_code, $count, $company->country);
        }catch(\Exception $e){
            return response([
                'error'     => $notFoundMessage,
                'available' => false
            ], 400);
        }

        if( ! count($numbers) )
            return response([
                'error'     => $notFoundMessage,
                'available' => false
            ], 400);

        return response([
            'available' => true,
            'count'     => count($numbers)
        ]);
    }

     /**
     * Check that phone numbers are available for the provided area codes
     * 
     */
    public function checkTollFreeNumbersAvailable(Request $request, Company $company)
    {
        $rules = [
            'count' => 'digits_between:1,2'
        ];

        $validator = Validator::make($request->input(), $rules);
        if( $validator->fails() ){
            return response([
                'error' => $validator->errors()->first()
            ], 400);
        }

        $count   = intval($request->count) ?: 1;

        $numbers = PhoneNumber::listAvailableTollFree($count, $company->country);

        return response([
            'available' => count($numbers) == $count
        ]);
    }
}
