<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use DeviceDetector\DeviceDetector;
use App\Models\WebProfile;
use App\Models\WebProfileIdentity;
use App\Models\WebDevice;
use App\Models\WebSession;
use App\Models\Company\Campaign;
use App\Models\Company\CampaignDomain;
use App\Models\Company\PhoneNumberPool;
use Validator;

class WebSessionController extends Controller
{
    protected $sessionUUIDKey         = 'mkf_session_uuid'; // http only - only visible by current domain
    
    protected $sessionHistoryKey      = 'mkf_session_history'; // http only - only visible by current domain

    protected $sessionPhoneKey        = 'mkf_session_phone'; // NOT http only - only visible by current domain

    protected $profileIdentityUUIDKey = 'mkf_pi_uuid'; // http only

    protected $deviceUUIDKey          = 'mkf_device_uuid'; // http only

    /**
     * Create a new session
     * 
     * @param Request $request    The incoming request
     * 
     * @return Response
     */
    public function create(Request $request)
    {
        //
        //  If this person already has an active session, stop
        //
        if( $sessionUUID = $request->cookie($this->sessionUUIDKey) ){
            return response([
                'error' => 'Session in progress'
            ], 400);
        }

        //
        //  Pull in device info and block bots and unknown devices from creating sessions
        //
        $ua = !empty($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
        $dd = new DeviceDetector($ua);
        $dd->parse();
        if( $dd->isBot() ){
            return response([
                'error' => 'No bots allowed!'
            ], 409);
        }
        
        //
        //  We don't know this person
        // 
        $ip = $request->header('X-Forwarded-For') ?: $request->ip();
        
        //  Check if this is part of a campaign
        $campaign       = null;
        $campaignDomain = null;
        if( $campaignDomainUUID = $request->campaign_domain_uuid ){
            //  Make sure the campaign exists
            $campaignDomain = CampaignDomain::where('uuid', $campaignDomainUUID)
                                            ->first();
            if( $campaignDomain )
                $campaign = Campaign::find($campaignDomain->campaign_id);
        }
        
        //  
        //  See if this person is coming from another site we know of
        //
        $profileIdentity = null;
        $profile         = null;
        if( $profileIdentityUUID = $request->cookie($this->profileIdentityUUIDKey) ){
            //  Make sure this identity exists
            $profileIdentity = WebProfileIdentity::where('uuid', $profileIdentityUUID)
                                                    ->first();
            if( $profileIdentity )
                $profile = WebProfile::find($profileIdentity->profile_id);

        }

        //  
        //  See if this person is using a device we already know about
        //
        $device = null;
        if( $deviceUUID = $request->cookie($this->deviceUUIDKey) ){
            $device = WebDevice::where('uuid', $deviceUUID)
                               ->first();
        }

        //
        //
        //  Create any new resources required
        //  
        //

        //
        //  Create profile
        //
        if( ! $profile ){
            $profile = WebProfile::create([
                'uuid' => Str::uuid()
            ]);
        }

        //  
        //  Create profile identity
        //
        if( ! $profileIdentity ){
            $profileIdentity = WebProfileIdentity::create([
                'uuid'                  => Str::uuid(),
                'web_profile_id'        => $profile->id,
                'campaign_domain_id'    => $campaignDomain ? $campaignDomain->id : null
            ]);
        }

        //
        //  Create device
        //    
        if( ! $device ){
            $client = $dd->getClient();
            $os     = $dd->getOs();
            $device = WebDevice::create([
                'web_profile_identity_id'    => $profileIdentity->id,
                'uuid'                       => Str::uuid(),
                'ip'                         => $ip,
                'width'                      => intval($request->device_width) ?: null,
                'height'                     => intval($request->device_height) ?: null,
                'type'                       => substr($dd->getDeviceName(), 0, 128) ?: null,
                'brand'                      => substr($dd->getBrandName(), 0, 128)  ?: null,
                'os'                         => $os && ! empty($os['name']) ? substr($os['name'], 0, 128) : null,
                'os_version'                 => $os && ! empty($os['version']) ? substr($os['version'], 0, 128) : null,
                'browser'                    => $client && ! empty($client['name']) ? substr($client['name'], 0, 128) : null,
                'browser_version'            => $client && ! empty($client['version']) ? substr($client['version'], 0, 128) : null,
                'browser_engine'             => $client && ! empty($client['engine'])  ? substr($client['engine'], 0, 128)  : null,
            ]);
        }

        //
        //  If this IS tied to an active web campaign, assign a phone number 
        //
        $phoneNumber     = null;
        $phoneNumberPool = null;
        if( $campaign && $campaign->isActive() && $campaign->type == Campaign::TYPE_WEB ){
            $phoneNumberPool = PhoneNumberPool::where('campaign_id', $campaign->id)
                                              ->first();
            if( $phoneNumberPool ){
                $preferredPhone     = $request->cookie($this->sessionPhoneKey) ? json_decode($request->cookie($this->sessionPhoneKey)) : null;
                $preferredPhoneUUID = $preferredPhone && !empty($preferredPhone->uuid) ? $preferredPhone->uuid : null;
                $phoneNumber        = $phoneNumberPool->assignPhoneNumber($preferredPhoneUUID);
            }
        }

        //
        //  Now, create the session
        //
        $session = WebSession::create([
            'uuid'                      => Str::uuid(),
            'web_profile_identity_id'   => $profileIdentity->id,
            'web_device_id'             => $device->id,
            'campaign_domain_id'        => $campaignDomain ? $campaignDomain->id : null,
            'campaign_id'               => $campaign ? $campaign->id : null,
            'phone_number_pool_id'      => $phoneNumberPool ? $phoneNumberPool->id : null,
            'phone_number_id'           => $phoneNumber ? $phoneNumber->id : null
        ]);

        //
        //  Get a list of sessions stored on user's device and remove any other active sessions
        //
        $sessionHistory = [];
        if( $historyList = $request->cookie($this->sessionHistoryKey) ){
            $sessionHistory = explode(',', $historyList);
            //  End any existing sessions from this site
            WebSession::whereIn('uuid', $sessionHistory)
                      ->whereNull('ended_at')
                      ->updated([
                          'ended_at' =>date('Y-m-d H:i:s')
                      ]);
        }

        //  Add session to end of session history
        $sessionHistory[] = $session->uuid;
        $sessionHistory   = implode(',', $sessionHistory);

        //  Set cookies for the values we care about and send the others to the client for them to store as needed
        $longCookieLifetime    = 60 * 24 * 365 * 99; // Store cookie for 99 years...
        $cookieDomain          = env('COOKIE_DOMAIN');
        $sessionCookieLifetime = 0;

        return response([
            'message' => 'created'
        ], 201)->withCookie(cookie($this->sessionUUIDKey, $session->uuid, $sessionCookieLifetime, '/')) // Lives with session
               ->withCookie(cookie($this->sessionPhoneKey, $phoneNumber ? json_encode($phoneNumber) : null, $sessionCookieLifetime, '/')) // Lives with session
               ->withCookie(cookie($this->sessionHistoryKey, $sessionHistory, $longCookieLifetime, '/')) // Store forever, but only on the requesting website
               ->withCookie(cookie($this->profileIdentityUUIDKey, $profileIdentity->uuid, $longCookieLifetime, '/', $cookieDomain)) // Store forever on this domain
               ->withCookie(cookie($this->deviceUUIDKey, $device->uuid, $longCookieLifetime, '/', $cookieDomain)); // Store forever on this domain
    }
}
