<?php

namespace App\Http\Controllers\Company\Campaign;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use \App\Models\Company;
use \App\Models\Company\Campaign;
use \App\Models\Company\CampaignDomain;
use Validator;

class DomainController extends Controller
{
    protected $domainPattern = '/(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9][a-z0-9-]{0,61}[a-z0-9]/i';
    /**
     * Create campaign spend
     * 
     * @param Request $request      
     * @param Company $company
     * @param Campaign $campaign
     * 
     * @return Response
     */
    public function create(Request $request, Company $company, Campaign $campaign)
    {
        $rules = [
            'domain' => [
                'bail',
                'required',
                'regex:' . $this->domainPattern
            ],
        ];

        $messages = [
            'domain.regex' => 'Domain format is invalid'
        ];

        $validator = Validator::make($request->input(), $rules, $messages);
        if( $validator->fails() ){
            return response([
                'error' => $validator->errors()->first()
            ], 400);
        }

        $campaignDomain = CampaignDomain::create([
            'campaign_id' => $campaign->id,
            'domain'      => $request->domain
        ]);

        return response([
            'message'           => 'created',
            'campaign_domain'   => $campaignDomain,
        ], 201);
    }

    /**
     * Update campaign spend
     * 
     * @param Request $request      
     * @param Company $company
     * @param Campaign $campaign
     * @param CampaignDomain $domain
     * 
     * @return Response
     */
    public function update(Request $request, Company $company, Campaign $campaign, CampaignDomain $domain)
    {
        $rules = [
            'domain' => [
                'bail',
                'required',
                'regex:' . $this->domainPattern
            ],
        ];

        $messages = [
            'domain.regex' => 'Domain format is invalid'
        ];

        $validator = Validator::make($request->input(), $rules, $messages);
        if( $validator->fails() ){
            return response([
                'error' => $validator->errors()->first()
            ], 400);
        }

        $domain->domain = $request->domain;
        $domain->save();

        return response([
            'message'         => 'updated',
            'campaign_domain' => $domain
        ]);
    }

    /**
     * Delete phone numbers from campaign
     * 
     * @param Request $request      
     * @param Company $company
     * @param Campaign $campaign
     * @param CampaignDomain $domain
     * 
     * @return Response
     */
    public function delete(Request $request, Company $company, Campaign $campaign, CampaignDomain $domain)
    {
        $domain->delete();

        return response([
            'message' => 'deleted'
        ]);
    }
}
