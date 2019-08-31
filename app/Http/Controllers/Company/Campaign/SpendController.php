<?php

namespace App\Http\Controllers\Company\Campaign;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use \App\Models\Company;
use \App\Models\Company\Campaign;
use \App\Models\Company\CampaignSpend;
use Validator;
use DateTime;
use DateTimeZone;

class SpendController extends Controller
{
   /**
     * Create campaign spend
     * 
     * @param Request 
     * @param Company
     * @param Campaign
     * 
     * @return Response
     */
    public function create(Request $request, Company $company, Campaign $campaign)
    {
        $rules = [
            'from_date' => 'required|date',
            'to_date'   => 'required|date',
            'total'     => 'required|numeric'
        ];

        $validator = Validator::make($request->input(), $rules);
        if( $validator->fails() ){
            return response([
                'error' => $validator->errors()->first()
            ], 400);
        }

        $user     = $request->user();
        $userTZ   = new DateTimeZone($user->timezone);
        $newTZ    = new DateTimeZone('UTC');

        $fromDate = new DateTime($request->from_date, $userTZ);
        $toDate   = new DateTime($request->to_date, $userTZ);

        $fromDate->setTimezone($newTZ);
        $toDate->setTimezone($newTZ);

        $campaignSpend = CampaignSpend::create([
            'campaign_id' => $campaign->id,
            'from_date'   => $fromDate->format('Y-m-d H:i:s'),
            'to_date'     => $toDate->format('Y-m-d H:i:s'),
            'total'       => floatval($request->total)
        ]);

        return response([
            'message'        => 'created',
            'campaign_spend' => $campaignSpend,
        ], 201);
    }

    /**
     * Update campaign spend
     * 
     * @param Request 
     * @param Company
     * @param Campaign
     * @param CampaignSpend
     * 
     * @return Response
     */
    public function update(Request $request, Company $company, Campaign $campaign, CampaignSpend $campaignSpend)
    {
        $rules = [
            'from_date' => 'required|date',
            'to_date'   => 'required|date',
            'total'     => 'required|numeric'
        ];

        $validator = Validator::make($request->input(), $rules);
        if( $validator->fails() ){
            return response([
                'error' => $validator->errors()->first()
            ], 400);
        }

        $user     = $request->user();
        $userTZ   = new DateTimeZone($user->timezone);
        $newTZ    = new DateTimeZone('UTC');

        $fromDate = new DateTime($request->from_date, $userTZ);
        $toDate   = new DateTime($request->to_date, $userTZ);

        $fromDate->setTimezone($newTZ);
        $toDate->setTimezone($newTZ);

        $campaignSpend->from_date = $fromDate->format('Y-m-d H:i:s');
        $campaignSpend->to_date   = $toDate->format('Y-m-d H:i:s');
        $campaignSpend->total     = floatval($request->total);

        return response([
            'message'        => 'created',
            'campaign_spend' => $campaignSpend
        ]);
    }

    /**
     * Delete phone numbers from campaign
     * 
     * @param Request 
     * @param Company
     * @param Campaign
     * @param CampaignSpend
     * 
     * @return Response
     */
    public function delete(Request $request, Company $company, Campaign $campaign, CampaignSpend $campaignSpend)
    {
        $campaignSpend->delete();

        return response([
            'message' => 'deleted'
        ]);
    }
}
