<?php

namespace App\Http\Controllers\Company\Campaign;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use \App\Models\Company;
use \App\Models\Company\Campaign;
use \App\Models\Company\CampaignTarget;
use \App\Rules\Company\CampaignTargetRule;
use Validator;

class TargetController extends Controller
{
    /**
     * Create campaign target
     * 
     * @param Request $request      
     * @param Company $company
     * @param Campaign $campaign
     * 
     * @return Response
     */
    public function create(Request $request, Company $company, Campaign $campaign)
    {
        //  Make suret this is a web campaign
        if( $campaign->type != Campaign::TYPE_WEB ){
            return response([
                'error' => 'Only web campaigns can have associated targets'
            ], 400);
        }

        $rules = [
           'rules' => [
               'bail',
               'required',
               'json',
                new CampaignTargetRule(),
           ]
        ];

        $validator = Validator::make($request->input(), $rules);
        if( $validator->fails() ){
            return response([
                'error' => $validator->errors()->first()
            ], 400);
        }

        if( CampaignTarget::where('campaign_id', $campaign->id)->count() > 0 ){
            return response([
                'error' => 'Campaigns can only have a single target'
            ], 400);
        }

        $campaignTarget = CampaignTarget::create([
            'campaign_id' => $campaign->id,
            'rules'       => $request->rules,
        ]);

        return response([
            'message'         => 'created',
            'campaign_target' => $campaignTarget,
        ], 201);
    }

    /**
     * Update campaign target
     * 
     * @param Request $request      
     * @param Company $company
     * @param Campaign $campaign
     * @param CampaignTarget $target
     * 
     * @return Response
     */
    public function update(Request $request, Company $company, Campaign $campaign, CampaignTarget $target)
    {
        $rules = [
            'rules' => [
                'bail',
                'required',
                'json',
                    new CampaignTargetRule(),
            ]
        ];

        $validator = Validator::make($request->input(), $rules);
        if( $validator->fails() ){
            return response([
                'error' => $validator->errors()->first()
            ], 400);
        }

        $target->rules = $request->rules;
        $target->save();

        return response([
            'message'         => 'updated',
            'campaign_target' => $target,
        ], 200);
    }

    /**
     * Delete phone numbers from campaign
     * 
     * @param Request $request      
     * @param Company $company
     * @param Campaign $campaign
     * @param CampaignSpend $spend
     * 
     * @return Response
     */
    public function delete(Request $request, Company $company, Campaign $campaign, CampaignTarget $target)
    {
        $target->delete();

        return response([
            'message' => 'deleted'
        ]);
    }
}
