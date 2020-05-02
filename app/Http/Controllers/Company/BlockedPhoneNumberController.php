<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Company;
use App\Models\Company\PhoneNumber;
use App\Models\BlockedPhoneNumber;
use App\Models\BlockedPhoneNumber\BlockedCall;
use App\Rules\BlockedPhoneNumbersRule;
use Validator;
use DB;

class BlockedPhoneNumberController extends Controller
{
    static $fields = [
        'blocked_phone_numbers.name',
        'blocked_phone_numbers.number',
        'blocked_phone_numbers.created_at',
        'blocked_phone_numbers.updated_at',
        'call_count'
    ];

    /**
     * List blocked phone numbers
     * 
     */
    public function list(Request $request, Company $company)
    {
        $query = BlockedPhoneNumber::select([
                                        'blocked_phone_numbers.*', 
                                        DB::raw('(SELECT count(*) FROM blocked_calls WHERE blocked_calls.blocked_phone_number_id = blocked_phone_numbers.id) AS call_count')
                                    ])
                                    ->where('blocked_phone_numbers.company_id', $company->id);

        return parent::results(
            $request,
            $query,
            [],
            self::$fields,
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
        
        $companyBlockedNumbers   = BlockedPhoneNumber::where('company_id', $company->id)->get();
        $existingBlockedNumbers  = [];
        foreach( $companyBlockedNumbers as $existingBlockedNumber ){
            $existingBlockedNumbers[] = $existingBlockedNumber->country_code . $existingBlockedNumber->number;
        }

        $wholeNumbers = [];
        $inserts      = [];
        $numbers      = json_decode($request->numbers, true);
        $createdAt    = now();

        foreach($numbers as $number){
            //  Check for uniqueness in set, but be forgiving
            $wholeNumber = $number['number'];
            if( in_array($wholeNumber, $wholeNumbers) )
                continue;

            //  Check for uniqueness in existing records, still be forgiving
            if( in_array($wholeNumber, $existingBlockedNumbers) )
                continue;

            $wholeNumbers[]         = $wholeNumber;
            $number['country_code'] = PhoneNumber::countryCode($wholeNumber) ?: null;
            $number['number']       = PhoneNumber::number($wholeNumber);
            $number['account_id']   = $company->account_id;
            $number['company_id']   = $company->id;
            $number['user_id']      = $user->id;
            $number['created_at']   = $createdAt;
            $number['created_by']   = $user->id;

            $inserts[]              = $number;
        }

        if( ! count($inserts) ){
            return response([
                'error' => 'All blocked numbers provided already exist'
            ], 400);
        }

        BlockedPhoneNumber::insert($inserts);

        $blockedNumbers = BlockedPhoneNumber::where('account_id', $user->account_id)
                                            ->where('company_id', $company->id)
                                            ->where('created_at', $createdAt)
                                            ->get();

        return response($blockedNumbers);
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

    /**
     * Bulk delete
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

        $blockedNumberIds = array_values(json_decode($request->ids, true) ?: []);
        $blockedNumberIds = array_filter($blockedNumberIds, function($item){
            return is_string($item) || is_numeric($item);
        });
        $blockedNumbers = BlockedPhoneNumber::whereIn('id', $blockedNumberIds)
                                            ->where('company_id', $company->id)
                                            ->get()
                                            ->toArray();

        $blockedNumberIds = array_column($blockedNumbers, 'id');
        if( count($blockedNumberIds) ){
            BlockedPhoneNumber::whereIn('id', $blockedNumberIds)->delete();
            BlockedCall::whereIn('blocked_phone_number_id', $blockedNumberIds)->delete();
        }

        return response([
            'message' => 'Deleted.',
            'count'   => count($blockedNumberIds)
        ]);
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
            BlockedPhoneNumber::class,
            $request,
            [],
            self::$fields,
            'blocked_phone_numbers.created_at'
        );
    }
}
