<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Company;
use App\Models\Company\PhoneNumber;
use App\Models\BlockedPhoneNumber;
use App\Rules\BlockedPhoneNumbersRule;
use Validator;
use DB;

class BlockedPhoneNumberController extends Controller
{
    /**
     * List blocked phone numbers
     * 
     */
    public function list(Request $request, Company $company)
    {
        $rules = [ 
            'order_by'  => 'in:blocked_phone_numbers.name,blocked_phone_numbers.number,blocked_phone_numbers.created_at, call_count' 
        ];

        $searchFields = [
            'companies.name',
            'companies.industry'
        ];

        $query = BlockedPhoneNumber::select(['blocked_phone_numbers.*', DB::raw('(SELECT count(*) FROM blocked_calls WHERE blocked_calls.blocked_phone_number_id = blocked_phone_numbers.id) AS call_count')])
                                   ->where('blocked_phone_numbers.company_id', $company->id);

        return parent::results(
            $request,
            $query,
            $rules,
            $searchFields,
            'blocked_phone_numbers.created_at'
        );
    }

    /**
     * Create a blocked phone number
     * 
     */
    public function create(Request $request, Company $company)
    {
        $user = $request->user();

        $validator = Validator::make($request->input(), [
            'numbers'        => [
                'bail', 
                'required', 
                'json', 
                new BlockedPhoneNumbersRule()
            ]
        ]);

        if( $validator->fails() ){
            return response([
                'error' => $validator->errors()->first()
            ], 400);
        }

        $numbers = json_decode($request->numbers, true);
        $inserts = [];
        $batchId = str_random(16);

        foreach($numbers as $number){
            $wholeNumber            = $number['number'];
            $number['country_code'] = PhoneNumber::countryCode($wholeNumber) ?: null;
            $number['number']       = PhoneNumber::number($wholeNumber);
            $number['account_id']   = $company->account_id;
            $number['company_id']   = $company->id;
            $number['batch_id']     = $batchId;
            $number['created_at']   = now();

            $inserts[]             = $number;
        }

        BlockedPhoneNumber::insert($inserts);

        return response(BlockedPhoneNumber::where('company_id', $company->id)->get(), 201);
    }

    /**
     * View a blocked number
     * 
     */
    public function read(Request $request, Company $company, BlockedPhoneNumber $blockedPhoneNumber)
    {
        $blockedPhoneNumber->call_count;

        return response($blockedPhoneNumber);
    }

    /**
     * Update a blocked phone number
     * 
     */
    public function update(Request $request, Company $company, BlockedPhoneNumber $blockedPhoneNumber)
    {
        $validator = Validator::make($request->input(), [
            'name' => 'bail|max:64',
        ]);

        if( $validator->fails() ){
            return response([
                'error' => $validator->errors()->first()
            ], 400);
        }

        if( $request->filled('name') && $request->name !== $blockedPhoneNumber->name ){
            $blockedPhoneNumber->name = $request->name;
            $blockedPhoneNumber->save();
        }

        $blockedPhoneNumber->call_count;

        return response($blockedPhoneNumber);
    }

    /**
     * Delete a blocked phone number
     * 
     */
    public function delete(Request $request, Company $company, BlockedPhoneNumber $blockedPhoneNumber)
    {
        $blockedPhoneNumber->delete();

        return response([
            'message' => 'Deleted.'
        ]);
    }
}
