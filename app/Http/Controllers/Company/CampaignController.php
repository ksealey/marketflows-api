<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Company;
use App\Models\Company\PhoneNumber;
use App\Models\Company\PhoneNumberPool;
use App\Models\Company\Campaign;
use App\Models\Company\CampaignPhoneNumberPool;
use App\Models\Company\CampaignPhoneNumber;
use App\Models\Company\CampaignDomain;
use App\Rules\Company\CampaignRule;
use App\Rules\Company\PhoneNumberPoolRule;
use App\Rules\Company\PhoneNumberRule;
use App\Jobs\BuildAndPublishCompanyJs;
use DateTime;
use DateTimeZone;
use Validator;
use DB;

class CampaignController extends Controller
{
    /**
     * List phone numbers
     * 
     * @param Illuminate\Http\Request $request      The incoming request
     * @param Illuminate\Http\Company $company      The associated company
     * 
     * @return Illuminate\Http\Response
     */
    public function list(Request $request, Company $company)
    {
        $limit  = intval($request->limit) ?: 25;
        $page   = intval($request->page) ? intval($request->page) - 1 : 0;
        $search = $request->search;

        $query = Campaign::where('company_id', $company->id);
        
        if( $search ){
            $query->where('name', 'like', '%' . $search . '%');
        }

        $resultCount = $query->count();
        $records     = $query->offset($page * $limit)
                             ->limit($limit)
                             ->get();

        return response([
            'message'       => 'success',
            'campaigns'     => $records,
            'result_count'  => $resultCount,
            'limit'         => $limit,
            'page'          => $page + 1,
            'total_pages'   => ceil($resultCount / $limit)
        ]);
    }

    /**
     * Create new resource
     * 
     * @param Illuminate\Http\Request $request      The incoming request
     * @param Illuminate\Http\Company $company      The associated company
     * 
     * @return Illuminate\Http\Response
     */
    public function create(Request $request, Company $company)
    {
        $rules = [
            'name'              => 'bail|required|max:255',
            'type'              => 'bail|required|in:' . implode(',', Campaign::types()),
            'phone_numbers'     => [
                'bail', 
                'array', 
                'required_without:phone_number_pool', 
                new PhoneNumberRule($company->id)
            ],
            'phone_numbers.*'   => 'numeric',
            'phone_number_pool' => [
                'bail', 
                'numeric',
                'required_without:phone_numbers',
                'required_if:type,' . Campaign::TYPE_WEB, 
                new PhoneNumberPoolRule($company->id)
            ],
            'active'            => 'required|bool',
        ]; 

        $validator = Validator::make($request->input(), $rules);

        //  Require domains for web campaigns
        $validator->sometimes('domains', 'required|array', function($input){
            return $input->type == Campaign::TYPE_WEB;
        });
        $validator->sometimes('domains.*', ['required', 'max:1024', 'regex:/(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9][a-z0-9-]{0,61}[a-z0-9]/'], function($input){
            return $input->type == Campaign::TYPE_WEB;
        });

        if( $validator->fails() ){
            return response([
                'error' =>  $validator->errors()->first()
            ], 400);
        }
        
        DB::beginTransaction();

        //  Make campaign
        $campaign = Campaign::create([
            'company_id'    => $company->id,
            'created_by'    => $request->user()->id,
            'name'          => $request->name,
            'type'          => $request->type,
            'activated_at'  => $request->active ? date('Y-m-d H:i:s') : null  
        ]);

        //  Create campaign domains when it's web  
        if( $request->type == Campaign::TYPE_WEB ){
            $insert = [];
            foreach( $request->domains as $domain ){
                $insert[] = [
                    'campaign_id'     => $campaign->id,
                    'domain'          => $domain,
                    'created_at'      => now(),
                    'updated_at'      => now()
                ];
            }
            CampaignDomain::insert($insert);
        }

        //  Tie phone numbers
        if( $request->phone_number_pool ){
            CampaignPhoneNumberPool::create([
                'campaign_id'           => $campaign->id,
                'phone_number_pool_id'  => $request->phone_number_pool 
            ]);
        }elseif( $request->phone_numbers ){
            $insert         = [];
            $phoneNumberIds = is_string($request->phone_numbers) ? json_decode($request->phone_numbers) : $request->phone_numbers;
            foreach( $phoneNumberIds as $phoneNumberId ){
                $insert[] = [
                    'campaign_id'     => $campaign->id,
                    'phone_number_id' => $phoneNumberId,
                    'created_at'      => now(),
                    'updated_at'      => now()
                ];
            } 
            CampaignPhoneNumber::insert($insert);
        }

        DB::commit();

        if( $campaign->type == Campaign::TYPE_WEB && $campaign->active() )
            BuildAndPublishCompanyJs::dispatch($company);

        return response([
            'campaign' => $campaign
        ], 201);
    }

    /**
     * Read new resource
     * 
     */
    public function read(Request $request, Company $company, Campaign $campaign)
    {
        $campaign->phone_numbers  = PhoneNumber::whereIn('id', function($query) use($campaign){
            $query->select('phone_number_id')
                  ->from('campaign_phone_numbers')
                  ->where('campaign_id', $campaign->id);
        })->get();

        $campaign->phone_number_pool = PhoneNumberPool::whereIn('id', function($query) use($campaign){
            $query->select('phone_number_pool_id')
                  ->from('campaign_phone_number_pools')
                  ->where('campaign_id', $campaign->id);
        })->first();
    
        return response([
            'campaign' => $campaign
        ]);
    }

    /**
     * Update Resource
     * 
     */
    public function update(Request $request, Company $company, Campaign $campaign)
    {
        if( $campaign->suspended_at ){
            return response([
                'error' => 'You cannot update a suspended campaign'
            ], 400);
        }

        $rules = [
            'name'              => 'bail|required|max:255',
            'phone_numbers'     => [
                'bail', 
                'array', 
                'required_without:phone_number_pool', 
                new PhoneNumberRule($company->id, $campaign->id)
            ],
            'phone_numbers.*'   => 'numeric',
            'phone_number_pool' => [
                'bail', 
                'numeric',
                'required_without:phone_numbers',
                'required_if:type,' . Campaign::TYPE_WEB, 
                new PhoneNumberPoolRule($company->id, $campaign->id)
            ],
            'active'            => 'required|bool'
        ]; 

        $validator = Validator::make($request->input(), $rules);

        //  Require domains for web campaigns
        $validator->sometimes('domains', 'required|array', function($input){
            return $input->type == Campaign::TYPE_WEB;
        });

        $validator->sometimes('domains.*', ['required', 'string', 'max:1024', 'regex:/(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9][a-z0-9-]{0,61}[a-z0-9]/'], function($input){
            return $input->type == Campaign::TYPE_WEB;
        });

        if( $validator->fails() ){
            return response([
                'error' =>  $validator->errors()->first()
            ], 400);
        }

        DB::beginTransaction();

        //  Update campaign
        if( $campaign->name != $request->name){
            $campaign->name = $request->name;
            $campaign->save();
        }
        

        //  Update phone links
        if( $request->phone_number_pool ){
            $phoneNumberPoolPivot = CampaignPhoneNumberPool::where('campaign_id', $campaign->id)->first();
            if( ! $phoneNumberPoolPivot ){
                //  Switch from phone number to pool
                //      - Remove phone number links
                CampaignPhoneNumber::where('campaign_id', $campaign->id)->delete();
                //      - Add phone number pool link
                CampaignPhoneNumberPool::create([
                    'campaign_id'           => $campaign->id,
                    'phone_number_pool_id'  => $request->phone_number_pool 
                ]);
            }elseif($phoneNumberPoolPivot->phone_number_pool_id != $request->phone_number_pool ){
                //  Updated pool
                $phoneNumberPoolPivot->phone_number_pool_id = $request->phone_number_pool;
                $phoneNumberPoolPivot->save();
            }
        }elseif( $request->phone_numbers ){
            $insertIds        = [];
            $phoneNumberIds   = is_string($request->phone_numbers) ? json_decode($request->phone_numbers) : $request->phone_numbers;
            $phoneNumberPivot = CampaignPhoneNumber::where('campaign_id', $campaign->id)->get();
            
            if( ! count($phoneNumberPivot) ){
                //  Switch from pool to phone numbers
                //      - Remove phone number pool
                CampaignPhoneNumberPool::where('campaign_id', $campaign->id)->delete();
                //       - Add new numbers
                $insertIds = $phoneNumberIds;
            }else{
                //  Detach old phone numbers
                CampaignPhoneNumber::where('campaign_id', $campaign->id)
                                    ->whereNotIn('id', $phoneNumberIds)
                                    ->delete();

                //  Prep to insert any that don't exist
                $existingPhoneNumberIds = array_column($phoneNumberPivot->toArray(), 'phone_number_id');
                $insertIds              = array_diff($phoneNumberIds, $existingPhoneNumberIds);
            }

            //  Insert if there are new records
            if( count($insertIds) ){
                $insert = [];
                foreach( $insertIds as $phoneNumberId ){
                    $insert[] = [
                        'campaign_id'     => $campaign->id,
                        'phone_number_id' => $phoneNumberId,
                        'created_at'      => now(),
                        'updated_at'      => now()
                    ];
                } 
                
                CampaignPhoneNumber::insert($insert); 
            }
        }

        //  Update domains
        if( $campaign->type == Campaign::TYPE_WEB ){
            //   Remove domains from DB that are missing from request
            CampaignDomain::where('campaign_id', $campaign->id)
                            ->whereNotIn('domain', $request->domains)
                            ->delete();

            //  Add domains that are not found in DB
            foreach( $request->domains as $domain ){
                CampaignDomain::firstOrCreate([
                    'campaign_id' => $campaign->id,
                    'domain'      => $domain
                ]);
            }
        }

        DB::commit();

        if( $campaign->type == Campaign::TYPE_WEB && $campaign->active() )
            BuildAndPublishCompanyJs::dispatch($company);

        return response([
            'campaign' => $campaign
        ], 200);
    }


    /**
     * Delete resource
     * 
     */
    public function delete(Request $request, Company $company, Campaign $campaign)
    {
        //  Do not allow users to delete active campaigns
        if( $campaign->active() ){
            return response([
                'error' => 'You cannot delete active campaigns'
            ], 400);
        }

        $campaign->delete();

        return response([
            'message' => 'deleted'
        ]);
    }
}
