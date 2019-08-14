<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PhoneNumber;
use App\Models\PhoneNumberPool;
use App\Models\Campaign;
use App\Models\CampaignPhoneNumberPool;
use App\Models\CampaignPhoneNumber;
use App\Rules\CampaignRule;
use App\Rules\PhoneNumberPoolRule;
use App\Rules\PhoneNumberRule;
use DateTime;
use DateTimeZone;
use Validator;
use DB;

class CampaignController extends Controller
{
    /**
     * List resources
     * 
     */
    public function list(Request $request)
    {
        $rules = [
            'start' => 'numeric',
            'limit' => 'numeric',
        ];

        $validator = Validator::make($request->input(), $rules);
        if( $validator->fails() ){
            return response([
                'error' =>  $validator->errors()->first()
            ], 400);
        }

        $user  = $request->user();
        $query = Campaign::where('company_id', $user->company_id);
        if( $search = $request->search ){
            $query->where(function($query) use($search){
                $query->where('name', 'like', $search . '%');
                //  Additional conditions here ...
            });
        }

        $totalCount = $query->count();
        
        $query->offset($request->start ?: 0);
        $query->limit($request->limit ?: 25);

        $campaigns = $query->get();

        return response([
            'campaigns'    => $campaigns,
            'result_count' => count($campaigns),
            'total_count'  => $totalCount,
            'message'      => 'success'
        ]);

    }

    /**
     * Create new resource
     * 
     */
    public function create(Request $request)
    {
        $user  = $request->user();

        $rules = [
            'name'              => 'bail|required|max:255',
            'type'              => 'bail|required|in:' . implode(',', Campaign::types()),
            'starts_at'         => 'bail|required|date',
            'ends_at'           => ['bail', 'date'],
            'phone_numbers'     => ['bail','required_without:phone_number_pool', new PhoneNumberRule($user->company_id)],
            'phone_number_pool' => ['bail', 'numeric', 'required_without:phone_numbers',new PhoneNumberPoolRule($user->company_id)],
        ]; 

        $validator = Validator::make($request->input(), $rules);
        if( $validator->fails() ){
            return response([
                'error' =>  $validator->errors()->first()
            ], 400);
        }

        $fromTZ = new DateTimeZone($user->timezone); 
        $toTZ   = new DateTimeZone('UTC');

        $startsAt = null;
        if( $request->starts_at ){
            $startsAt = new DateTime($request->starts_at, $fromTZ);
            $startsAt->setTimezone($toTZ);
            $startsAt = $startsAt->format('Y-m-d H:i:s');
        }

        $endsAt = null;
        if( $request->ends_at ){
            $endsAt = new DateTime($request->ends_at, $fromTZ);
            $endsAt->setTimezone($toTZ);
            $endsAt = $endsAt->format('Y-m-d H:i:s');
        }
        
        DB::beginTransaction();

        //  Make campaign
        $campaign = Campaign::create([
            'company_id' => $user->company_id,
            'created_by' => $user->id,
            'name'       => $request->name,
            'type'       => $request->type,
            'starts_at'  => $startsAt,
            'ends_at'    => $endsAt
        ]);

        //  Tie phone numbers
        if( $request->phone_number_pool ){
            CampaignPhoneNumberPool::create([
                'campaign_id'           => $campaign->id,
                'phone_number_pool_id'  => $request->phone_number_pool 
            ]);

            $campaign->phone_number_pool = PhoneNumberPool::find($request->phone_number_pool);
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
            
            $campaign->phone_numbers = PhoneNumber::whereIn('id', $phoneNumberIds)
                                                  ->get();
        }

        DB::commit();

        return response([
            'campaign' => $campaign
        ], 201);
    }

    /**
     * Read new resource
     * 
     */
    public function read(Request $request, Campaign $campaign)
    {
        $user = $request->user();
        if( $campaign->company_id != $user->company_id )
            return response([
                'error' => 'Not found'
            ], 404);
        
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
    public function update(Request $request, Campaign $campaign)
    {
        $user = $request->user();
        if( $campaign->company_id != $user->company_id )
            return response([
                'error' => 'Not found'
            ], 404);

        $rules = [
            'name'              => 'bail|required|max:255',
            'type'              => 'bail|required|in:' . implode(',', Campaign::types()),
            'starts_at'         => 'bail|required|date',
            'ends_at'           => ['bail', 'date'],
            'phone_numbers'     => ['bail','required_without:phone_number_pool', new PhoneNumberRule($user->company_id, $campaign->id)],
            'phone_number_pool' => ['bail', 'numeric', 'required_without:phone_numbers',new PhoneNumberPoolRule($user->company_id, $campaign->id)],
        ]; 

        $validator = Validator::make($request->input(), $rules);
        if( $validator->fails() ){
            return response([
                'error' =>  $validator->errors()->first()
            ], 400);
        }

        if( $campaign->suspended_at ){
            return response([
                'error' => 'You cannot update a suspended campaign'
            ]);
        }

        $fromTZ = new DateTimeZone($user->timezone); 
        $toTZ   = new DateTimeZone('UTC');

        $startsAt = null;
        if( $request->starts_at ){
            $startsAt = new DateTime($request->starts_at, $fromTZ);
            $startsAt->setTimezone($toTZ);
            $startsAt = $startsAt->format('Y-m-d H:i:s');
        }

        $endsAt = null;
        if( $request->ends_at ){
            $endsAt = new DateTime($request->ends_at, $fromTZ);
            $endsAt->setTimezone($toTZ);
            $endsAt = $endsAt->format('Y-m-d H:i:s');
        }
        
        DB::beginTransaction();

        //  Update campaign
        $campaign->name       = $request->name;
        $campaign->type       = $request->type;
        $campaign->starts_at  = $startsAt;
        $campaign->ends_at    = $endsAt;
        $campaign->save();

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
            $campaign->phone_number_pool = PhoneNumberPool::find($request->phone_number_pool);
            $campaign->phone_numbers     = [];
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

            $campaign->phone_numbers = PhoneNumber::whereIn('id', $phoneNumberIds)
                                                  ->get();
            $campaign->phone_number_pool = null;
        }

        DB::commit();

        return response([
            'campaign' => $campaign
        ], 200);
    }


    /**
     * Delete resource
     * 
     */
    public function delete(Request $request, Campaign $campaign)
    {
        $user = $request->user();
        if( $campaign->company_id != $user->company_id )
            return response([
                'error' => 'Not found'
            ], 404);

        //  Do not allow users to delete active campaigns
        if( $campaign->activated_at && (! $campaign->ends_at || $campaign->ends_at > date('Y-m-d H:i:s')) ){
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
