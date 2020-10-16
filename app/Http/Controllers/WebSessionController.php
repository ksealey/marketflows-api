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
        $validator->sometimes('landing_url', 'required|url', function() use($request){
            return ! $request->cookie('landing_url');
        });
        $validator->sometimes('http_referrer', 'nullable|url', function()  use($request){
            return ! $request->cookie('http_referrer');
        });

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
                'error' => 'Invalid company'
            ], 404);
        }

        //
        //  If the use has an active session, stop here
        //
        if( $request->cookie('guuid') && $request->cookie('session_uuid') ){
            $session = KeywordTrackingPoolSession::where('uuid', $request->cookie('session_uuid'))
                                                 ->where('guuid', $request->cookie('guuid'))
                                                 ->whereNull('ended_at')
                                                 ->first();
            if( $session ){
                return response([
                    'message' => 'Session in progress'
                ]);
            }
        }

        $cookieDomain = explode('.', $request->getHttpHost());
        if( count($cookieDomain) > 2 ){
            $cookieDomain = array_slice($cookieDomain, count($cookieDomain) - 2, 2);
        }
        $cookieDomain = implode('.', $cookieDomain);

        $gUUID             = $request->cookie('guuid') ?: Str::uuid();
        $expirationMinutes = $company->tracking_expiration_days * 60 * 24;
        

        Cookie::queue('guuid', $gUUID, 60 * 24 * 365 * 100, '/', $cookieDomain); // Last forever forever
        Cookie::queue('init_complete', 1, $expirationMinutes, '/', $cookieDomain, false, false);

        //
        //  Determine if we should swap numbers at all
        //
        $httpReferrer = $request->cookie('http_referrer') ?: $request->http_referrer;
        $landingURL   = $request->cookie('landing_url')   ?: $request->landing_url;

        //  Allow frontend access until the time specified by the user
        Cookie::queue('http_referrer', $httpReferrer, $expirationMinutes, '/', $cookieDomain, false, false);
        Cookie::queue('landing_url', $landingURL, $expirationMinutes, '/', $cookieDomain, false, false);

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
        
        $contactId             = null;
        $phoneNumber           = null;
        $swapRules             = null;
        $sessionToken          = null;
        $session               = null;
        $sessionExpirationDays = 0;
        $pool                  = $company->keyword_tracking_pool; 
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
                                          ->update([
                                              'ended_at' => now(),
                                              'active'   => 0
                                          ]);
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
                'created_at'                => now()->format('Y-m-d H:i:s.u'),
                'last_activity_at'          => now(),
                'active'                    => 1,
                'end_after'                 => now()->addDays($company->tracking_expiration_days)
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
                $phoneNumber->save();
                break;
            }
        }

        if( $phoneNumber ){
            Cookie::queue('phone_uuid', $phoneNumber->uuid, $expirationMinutes, '/', $cookieDomain, false, false);
            Cookie::queue('phone_country_code', $phoneNumber->country_code, $expirationMinutes, '/', $cookieDomain, false, false);
            Cookie::queue('phone_number', $phoneNumber->number, $expirationMinutes, '/', $cookieDomain, false, false);
            Cookie::queue('swapping_targets', json_encode($swapRules->targets), $expirationMinutes, '/', $cookieDomain, false, false);
        }

        if( $session ){
            //  Allow frontend access for the length of the session
            Cookie::queue('session_ktp_id', $pool->id, $expirationMinutes, '/', $cookieDomain, false, false);
            Cookie::queue('session_uuid', $session->uuid, $expirationMinutes, '/', $cookieDomain, false, false);

            //  Http only, for the length of the session
            Cookie::queue('session_token', $sessionToken, $expirationMinutes, '/', $cookieDomain);
        }

        return response([
            'guuid'   => $gUUID,
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
                'error' => $validator->errors()->first()
            ], 400);
        }

        if( $request->cookie('session_uuid') && $request->cookie('session_token') ){
            $session = KeywordTrackingPoolSession::where('uuid', $request->cookie('session_uuid'))
                                                 ->whereNull('ended_at')
                                                 ->first();

            if( $session && password_verify($request->cookie('session_token'), $session->token) ){
                $session->last_url = $request->url;
                $session->active   = 1;
                $session->last_activity_at = now()->format('Y-m-d H:i:s.u');
                $session->save();

                return response([
                    'status' => 'OK'
                ]);
            }
        }

        return response([
            'error' => 'Session does not exist'
        ], 404);
    }

    public function keepAlive(Request $request)
    {
        if( $request->cookie('session_uuid') && $request->cookie('session_token') ){
            $session = KeywordTrackingPoolSession::where('uuid', $request->cookie('session_uuid'))
                                                ->whereNull('ended_at')
                                                ->first();

            if( $session && password_verify($request->cookie('session_token'), $session->token) ){
                $session->active           = 1;
                $session->last_activity_at = now()->format('Y-m-d H:i:s.u');
                $session->save();

                return response([
                    'status' => 'OK'
                ]);
            }
        }

        return response([
            'error' => 'Session does not exist'
        ], 404);
    }

    public function pauseSession(Request $request)
    {
        if( $request->cookie('session_uuid') && $request->cookie('session_token') ){
            $session = KeywordTrackingPoolSession::where('uuid', $request->cookie('session_uuid'))
                                                ->whereNull('ended_at')
                                                ->first();

            if( $session && password_verify($request->cookie('session_token'), $session->token) ){
                $session->active           = 0;
                $session->save();

                return response([
                    'status' => 'OK'
                ]);
            }
        }
    }

    public function numberStatus(Request $request)
    {
        if( $request->cookie('phone_uuid') && $request->cookie('guuid') ){
            $phoneNumber = PhoneNumber::where('uuid', $request->cookie('phone_uuid'))->first();
            if( $phoneNumber && ! $phoneNumber->disabled_at ){
                return response([
                    'status' => 'OK'
                ]);
            }
        }

        return response([
            'error' => 'Phone number does not exist'
        ], 404);
    }
}
