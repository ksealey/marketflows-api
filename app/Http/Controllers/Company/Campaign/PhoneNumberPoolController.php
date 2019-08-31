<?php

namespace App\Http\Controllers\Company\Campaign;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use \App\Models\Company;
use \App\Models\Company\Campaign;
use \App\Models\Company\CampaignPhoneNumberPool;
use \App\Rules\Company\PhoneNumberPoolRule;
use Validator;

class PhoneNumberPoolController extends Controller
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
            'phone_number_pool' => [
                'bail',
                'required', 
                new PhoneNumberPoolRule($company, $campaign)
            ]
        ];

        $validator = Validator::make($request->input(), $rules);
        if( $validator->fails() ){
            return response([
                'error' => $validator->errors()->first()
            ], 400);
        }

        //  Make sure there is not already a pool attached to this record
        if( CampaignPhoneNumberPool::where('campaign_id', $campaign->id)->count() > 0 ){
            return response([
                'error' => 'Campaigns can only have a single phone numbr pool'
            ], 400);
        }

        CampaignPhoneNumberPool::insert([
            'campaign_id'          => $campaign->id,
            'phone_number_pool_id' => $request->phone_number_pool
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
            'phone_number_pool' => [
                'bail',
                'required', 
                new PhoneNumberPoolRule($company, $campaign)
            ]
        ];

        $validator = Validator::make($request->input(), $rules);
        if( $validator->fails() ){
            return response([
                'error' => $validator->errors()->first()
            ], 400);
        }

        CampaignPhoneNumberPool::where('phone_number_pool_id', $request->phone_number_pool)
                           ->where('campaign_id', $campaign->id)
                           ->delete();

        return response([
            'message' => 'deleted'
        ]);
    }
}
