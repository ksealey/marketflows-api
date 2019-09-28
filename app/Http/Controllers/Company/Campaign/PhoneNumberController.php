<?php

namespace App\Http\Controllers\Company\Campaign;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use \App\Models\Company;
use \App\Models\Company\Campaign;
use \App\Models\Company\PhoneNumber;
use \App\Rules\Company\PhoneNumberRule;
use Validator;

class PhoneNumberController extends Controller
{
    /**
     * Attach phone numbers to campaign
     * 
     * @param Request 
     * @param Company
     * @param Campaign
     * 
     * @return Response
     */
    public function add(Request $request, Company $company, Campaign $campaign)
    {
        //  Not allowed for web
        if( $campaign->type == Campaign::TYPE_WEB ){
            return response([
                'error' => 'Web campaigns cannot be tied to orphan phone numbers, only phone number pools'
            ], 400);
        }

        $rules = [
            'phone_numbers' => [
                'bail',
                'required', 
                'array', 
                new PhoneNumberRule($company, $campaign)
            ],
            'phone_numbers.*' => 'bail|required|numeric'
        ];

        $validator = Validator::make($request->input(), $rules);
        if( $validator->fails() ){
            return response([
                'error' => $validator->errors()->first()
            ], 400);
        }

        $now    = date('Y-m-d H:i:s');
        PhoneNumber::whereIn('id', $request->phone_numbers)
                   ->update([
                       'campaign_id' => $campaign->id
                   ]);

        return response([
            'message' => 'created'
        ], 201);
    }

    /**
     * Delete phone numbers from campaign
     * 
     * @param Request 
     * @param Company
     * @param Campaign
     * 
     * @return Response
     */
    public function remove(Request $request, Company $company, Campaign $campaign)
    {
        $rules = [
            'phone_numbers' => [
                'bail',
                'required', 
                'array', 
                new PhoneNumberRule($company, $campaign)
            ],
            'phone_numbers.*' => 'bail|required|numeric'
        ];

        $validator = Validator::make($request->input(), $rules);
        if( $validator->fails() ){
            return response([
                'error' => $validator->errors()->first()
            ], 400);
        }

        PhoneNumber::whereIn('id', $request->phone_numbers)
                   ->update([
                       'campaign_id' => null
                   ]);

        return response([
            'message' => 'deleted'
        ]);
    }
}
