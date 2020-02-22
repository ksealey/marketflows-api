<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Company;
use App\Models\BlockedPhoneNumber;
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
        $query = BlockedPhoneNumber::select(['*', DB::raw('(SELECT COUNT(*) FROM calls WHERE calls.blocked_phone_number_id = blocked_phone_numbers.id) AS call_count')])
                                    ->where('company_id', $company->id);
        
        if( $request->search )
            $query->where(function($query) use($request){
                $query->where('name', 'like', '%' . $request->search . '%')
                      ->orWhere('number', 'like', '%' . $request->search . '%');
            });

        return parent::results(
            $request,
            $query,
            [ 'order_by'  => 'in:name,number,created_at,updated_at,call_count' ]
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
            'number'        => 'bail|required|numeric|digits_between:10,13',
            'name'          => 'bail|required|max:64',
        ]);

        if( $validator->fails() ){
            return response([
                'error' => $validator->errors()->first()
            ], 400);
        }

        $number = BlockedPhoneNumber::create([
            'account_id' => $user->account_id,
            'company_id' => $company->id,
            'user_id'    => $user->id,
            'name'       => $request->name,
            'number'     => $request->number
        ]);

        return response($number, 201);
    }

    /**
     * View a blocked number
     * 
     */
    public function read(Request $request, Company $company, BlockedPhoneNumber $blockedPhoneNumber)
    {
        $blockedPhoneNumber->calls;

        return response($blockedPhoneNumber);
    }

    /**
     * Update a blocked phone number
     * 
     */
    public function update(Request $request, Company $company, BlockedPhoneNumber $blockedPhoneNumber)
    {
        $validator = Validator::make($request->input(), [
            'name'          => 'bail|required|max:64',
        ]);

        if( $validator->fails() ){
            return response([
                'error' => $validator->errors()->first()
            ], 400);
        }

        $blockedPhoneNumber->name = $request->name;

        $blockedPhoneNumber->save();

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
