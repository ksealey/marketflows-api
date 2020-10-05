<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Models\Company;
use App\Models\Company\Contact;
use App\Models\Company\PhoneNumber;
use App\Models\Company\KeywordTrackingPool;
use App\Models\Company\KeywordTrackingPoolSession;
use Jenssegers\Agent\Agent;
use Cookie;

class WebSessionController extends Controller
{
    use \App\Traits\CanSwapNumbers;

    public function startSession(Request $request)
    {
        $rules = [
            'device_width'  => 'required|numeric',
            'device_height' => 'required|numeric',
            'company_id'    => 'required|numeric',
            'landing_url'   => 'required|url',
            'http_referrer' => 'nullable|url',
        ];

        $validator = validator($request->input(), $rules);
        if( $validator->fails() ){
            return response([
                'error' => 'Invalid request'
            ], 400);
        }

        $agent = new Agent();
        $agent->setUserAgent($request->header('User-Agent'));
        if( $agent->isRobot() ){
            return response([
                'error' => 'Not allowed'
            ], 403);
        }

        $company = Company::find($request->company_id);
        if( ! $company ){
            return response([
                'error' => 'Not found'
            ], 404);
        }
        
        //
        //  See if this user already has a session
        //
        $gUUID        = $request->cookie('guuid');
        $sessionUUID  = $request->cookie('session_uuid');
        $sessionToken = $request->cookie('session_token');
        if( $gUUID && $sessionUUID && $sessionToken ){ // An existing session
            //
            //  IF it's still active, return the same session
            //
            $session = KeywordTrackingPoolSession::where('uuid', $sessionUUID) 
                                                ->where('guuid', $gUUID) 
                                                ->whereNull('ended_at')
                                                ->first();

            if( $session && password_verify($sessionToken, $session->token) ){
                //  Make sure the pool exists, is enabled, and still owns the phone number
                $pool        = $session->keyword_tracking_pool;
                $phoneNumber = $session->phone_number;
                if( $pool && ! $pool->disabled_at && $phoneNumber && $phoneNumber->keyword_tracking_pool_id == $pool->id ){
                    $swapRules = $pool->swap_rules;
                    //
                    //  Set any cookies that may have been removed
                    //
                    $sessionExpirationDays = $swapRules->expiration_days * 60 * 24;
                    
                    Cookie::queue('swapping_targets', json_encode($swapRules->targets), $sessionExpirationDays);
                
                    Cookie::queue('session_ktp_id', $pool->id, $sessionExpirationDays);
                    Cookie::queue('session_uuid', $session->uuid, $sessionExpirationDays);
                    Cookie::queue('session_token', $sessionToken, $sessionExpirationDays);
        
                    Cookie::queue('phone_uuid', $phoneNumber->uuid, $sessionExpirationDays);
                    Cookie::queue('phone_country_code', $phoneNumber->country_code, $sessionExpirationDays);
                    Cookie::queue('phone_number', $phoneNumber->number, $sessionExpirationDays);
                
                    Cookie::queue('guuid', $session->guuid, 60 * 24 * 365 * 99); // 99 years
                    Cookie::queue('init', 1, $sessionExpirationDays);
                
                    return response([
                        'guuid' => $session->guuid,
                        'session' => [
                            'ktp_id'=> $session->keyword_tracking_pool_id,
                            'uuid'  => $session->uuid,
                            'token' => $sessionToken
                        ],
                        'phone'  => [
                            'uuid'          => $phoneNumber->uuid,
                            'number'        => $phoneNumber->number,
                            'country_code'  => $phoneNumber->country_code,
                        ],
                        'swapping' => [
                            'targets' => $swapRules->targets,
                        ]
                    ]); 
                }
            }
        }

        //
        //  Determine if we should swap numbers at all
        //
        $httpReferrer = $request->http_referrer;
        $landingURL   = $request->landing_url;
        $browserType  = $this->normalizeBrowserType($agent->browser());

        if( $agent->isDesktop() ){
            $deviceType = 'DESKTOP';
        }elseif( $agent->isTablet() ){
            $deviceType = 'TABLET';
        }elseif( $agent->isMobile() ){
            $deviceType = 'MOBILE';
        }else{
            $deviceType = 'OTHER';
        }
        
        $gUUID                 = $gUUID ?: Str::uuid();
        $contactId             = null;
        $phoneNumber           = null;
        $swapRules             = null;
        $sessionToken          = null;
        $session               = null;
        $sessionExpirationDays = 0;
        
        $pool = $company->keyword_tracking_pool;
        if( $pool && ! $pool->disabled_at && $this->swapRulesPass($pool->swap_rules, $browserType, $deviceType, $httpReferrer, $landingURL, $company->medium_param) ){
            $phoneNumber           = $pool->assignNumber();
            $swapRules             = $pool->swap_rules;
            $sessionToken          = str_random(40);
            $existingContact       = null;
            if( $request->cookie('guuid') ){
                $contact   = Contact::where('uuid', $request->cookie('guuid'))->first();
                $contactId = $contact ? $contact->id : null;
                KeywordTrackingPoolSession::where('guuid', $request->cookie('guuid'))
                                          ->whereNull('ended_at')
                                          ->update(['ended_at' => now()]);
            }

            $session = KeywordTrackingPoolSession::create([
                'contact_id'                => $contactId, // Preclaim session
                'guuid'                     => $gUUID,
                'uuid'                      => Str::uuid(),
                'keyword_tracking_pool_id'  => $pool->id,
                'phone_number_id'           => $phoneNumber->id,
                'device_width'              => $request->device_width,
                'device_height'             => $request->device_height,
                'device_type'               => $deviceType,
                'device_browser'            => $browserType,                
                'device_platform'           => str_replace(' ','_', strtoupper(substr($agent->platform(), 0, 64))),
                'http_referrer'             => $httpReferrer ? substr($httpReferrer, 0, 1024) : '',
                'landing_url'               => substr($landingURL, 0, 1024),
                'last_url'                  => substr($landingURL, 0, 1024),
                'token'                     => bcrypt($sessionToken),
                'created_at'                => now()->format('Y-m-d H:i:s.u')
            ]);
        }else{
            foreach($company->detached_phone_numbers as $detachedPhoneNumber){
                if( $detachedPhoneNumber->disabled_at 
                    || $detachedPhoneNumber->sub_category !== 'WEBSITE' 
                    || ! $this->swapRulesPass($detachedPhoneNumber->swap_rules, $browserType, $deviceType, $httpReferrer, $landingURL, $company->medium_param)
                ){  continue; }

                $phoneNumber = $detachedPhoneNumber;
                $swapRules   = $phoneNumber->swap_rules;
                
                $phoneNumber->last_assigned_at = now()->format('Y-m-d H:i:s.u');
                $phoneNumber->total_assignments++;
                $phoneNumber->save();
                break;
            }
        }

        if( $swapRules ){
            $sessionExpirationDays = $swapRules->expiration_days * 60 * 24;
            Cookie::queue('swapping_targets', json_encode($swapRules->targets), $sessionExpirationDays);
        }
        
        if( $session ){
            Cookie::queue('session_ktp_id', $pool->id, $sessionExpirationDays);
            Cookie::queue('session_uuid', $session->uuid, $sessionExpirationDays);
            Cookie::queue('session_token', $sessionToken, $sessionExpirationDays);
        }

        if( $phoneNumber ){
            Cookie::queue('phone_uuid', $phoneNumber->uuid, $sessionExpirationDays);
            Cookie::queue('phone_country_code', $phoneNumber->country_code, $sessionExpirationDays);
            Cookie::queue('phone_number', $phoneNumber->number, $sessionExpirationDays);
        }

        Cookie::queue('guuid', $gUUID, 60 * 24 * 365 * 99); // 99 years
        Cookie::queue('init', 1, $sessionExpirationDays);

        return response([
            'guuid' => $gUUID,
            'session' => $session ? [
                'ktp_id'=> $session->keyword_tracking_pool_id,
                'uuid'  => $session->uuid,
                'token' => $sessionToken
            ] : null,
            'phone'  => $phoneNumber ? [
                'uuid'          => $phoneNumber->uuid,
                'number'        => $phoneNumber->number,
                'country_code'  => $phoneNumber->country_code,
            ] : null,
            'swapping' => $swapRules ? [
                'targets' => $swapRules->targets,
            ] : null
        ]);
    }

    public function collect(Request $request)
    {
        $validator = validator($request->input(), [
            'url' => 'required|url',
        ]);

        if( $validator->fails() ){
            return response([
                'errors' => $validator->errors()->first()
            ], 400);
        }

        if( ! $request->cookie('session_uuid') || ! $request->cookie('session_token') ){
            return response([
                'errors' => 'Unauthorized'
            ], 403);
        }

        $session = KeywordTrackingPoolSession::where('uuid', $request->cookie('session_uuid'))
                                             ->whereNull('ended_at')
                                             ->first();

        if( ! $session || ! password_verify($request->cookie('session_token'), $session->token) ){
            return response([
                'errors' => 'Unauthorized'
            ], 403);
        }

        $session->last_url = $request->url;
        $session->save();

        return response([
            'message' => 'OK'
        ]);
    }

    public function endSession(Request $request)
    {
        if( ! $request->cookie('session_uuid') || ! $request->cookie('session_token') ){
            return response([
                'errors' => 'Unauthorized'
            ], 403);
        }

        $session = KeywordTrackingPoolSession::where('uuid', $request->cookie('session_uuid'))
                                             ->whereNull('ended_at')
                                             ->first();

        if( ! $session || ! password_verify($request->cookie('session_token'), $session->token) ){
            return response([
                'errors' => 'Unauthorized'
            ], 403);
        }

        $session->ended_at = now()->format('Y-m-d H:i:s.u');
        $session->save();

        return response([
            'message' => 'OK'
        ]);
    }
}
