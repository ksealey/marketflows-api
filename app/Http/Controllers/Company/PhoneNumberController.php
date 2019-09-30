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
            'message'       => 'success',
            'phone_numbers' => $records,
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
            'phone_number_pool' => ['bail', new PhoneNumberPoolRule($company)],
            'phone_number_config'=>['bail', 'required', 'numeric', new PhoneNumberConfigRule($company)],
            'number'            => 'bail|required|digits_between:10,13',
            'name'              => 'bail|required|max:255',
        ];

        $validator = Validator::make($request->input(), $rules);
        if( $validator->fails() ){
            return response([
                'error' => $validator->errors()->first()
            ], 400);
        }

        $user  = $request->user();

        //  Purchase a phone number
        try{
            $purchasedPhone = PhoneNumber::purchase($request->number);

            $phoneNumber = PhoneNumber::create([
                'uuid'                      => Str::uuid(),
                'company_id'                => $company->id,
                'created_by'                => $user->id,
                'phone_number_config_id'    => $request->phone_number_config,
                'external_id'               => $purchasedPhone->sid,
                'country_code'              => PhoneNumber::countryCode($purchasedPhone->phoneNumber),
                'number'                    => PhoneNumber::number($purchasedPhone->phoneNumber),
                'voice'                     => $purchasedPhone->capabilities['voice'],
                'sms'                       => $purchasedPhone->capabilities['sms'],
                'mms'                       => $purchasedPhone->capabilities['mms'],
                'phone_number_pool_id'      => $request->phone_number_pool,
                'name'                      => $request->name,
            ]);
        }catch(Exception $e){
            throw $e;
            return response([
                'error' => 'Unable to complete purchase - please try another number'
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
}
