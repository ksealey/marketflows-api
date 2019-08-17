<?php

namespace App\Http\Controllers\Open;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\PhoneNumber;
use App\Models\Campaign;
use DateTime;

class CampaignController extends Controller
{
    /**
     * Assign a phone number
     * 
     * @param Request $request
     * @param Campaign $campaign
     */
    public function assignPhone(Request $request, Campaign $campaign)
    {
        // Make sure the campaign is active
        if( !  $campaign->activated_at  
            || $campaign->suspended_at 
            || ($campaign->ends_at && $campaign->ends_at < date('Y-m-d H:i:s')) 
        ){
            return response([
                'error' => 'Campaign inactive'
            ], 400);
        }

        //  TODO: Connect to insights to look for a phone assignment where there is a matching fingerprint
        //  ...
        // 


        $phone = PhoneNumber::whereIn('id', function($query) use($campaign){
            $query->select('phone_number_id')
                  ->from('campaign_phone_numbers')
                  ->where('campaign_id', $campaign->id);
        })
        ->orderBy('last_assigned_at', 'ASC')
        ->first();

        if( ! $phone ){
            $phone = PhoneNumber::whereIn('phone_number_pool_id', function($query) use($campaign){
                $query->select('phone_number_pool_id')
                      ->from('campaign_phone_number_pools')
                      ->where('campaign_id', $campaign->id);
            })
            ->orderBy('last_assigned_at', 'ASC')
            ->first();
        }

        if( ! $phone )
            return response([
                'error' => 'No phone numbers fuond for campaign'
            ], 400);

        $now = new DateTime();
        $phone->last_assigned_at = $now->format('Y-m-d H:i:s.u');
        $phone->save();

        return response([
            'phone_number' => $phone
        ]);
    }
}
