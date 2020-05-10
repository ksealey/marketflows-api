<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Company\BlockedPhoneNumber;
use App\Models\Company\PhoneNumber;
use Validator;

class BlockedPhoneNumberController extends Controller
{
    /**
     * List blocked phone numbers
     * 
     */
    public function list(Request $request)
    {
        $user  = $request->user();
        $query = BlockedPhoneNumber::where('account_id', $user->account_id);
        
        if( $request->search )
            $query->where(function($query) use($request){
                $query->where('name', 'like', '%' . $request->search . '%')
                      ->orWhere('number', 'like', '%' . $request->search . '%');
            });

        return parent::results(
            $request,
            $query,
            ['order_by'  => 'in:name,number,created_at,updated_at']
        );
    }

    /**
     * Create a blocked phone number
     * 
     */
    public function create(Request $request)
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
            'account_id'    => $user->account_id,
            'company_id'    => null,
            'user_id'       => $user->id,
            'name'          => $request->name,
            'country_code'  => PhoneNumber::countryCode($request->number),
            'number'        => PhoneNumber::number($request->number),
        ]);

        return response($number, 201);
    }

    /**
     * View a blocked number
     * 
     */
    public function read(Request $request, BlockedPhoneNumber $blockedPhoneNumber)
    {
        $blockedPhoneNumber->calls;

        return response($blockedPhoneNumber);
    }

    /**
     * Update a blocked phone number
     * 
     */
    public function update(Request $request, BlockedPhoneNumber $blockedPhoneNumber)
    {
        $validator = Validator::make($request->input(), [
            'name' => 'bail|required|max:64',
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
    public function delete(Request $request, BlockedPhoneNumber $blockedPhoneNumber)
    {
        $blockedPhoneNumber->delete();

        return response([
            'message' => 'Deleted.'
        ]);
    }
}
