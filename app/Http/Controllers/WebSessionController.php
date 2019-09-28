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
    /**
     * Create a new session
     * 
     */
    public function create(Request $request)
    {
        //  If this person already has a session, there's nothing to do
        if( $sessionCookie = $request->cookie('mkf_session') ){
            return response([
                'message' => 'Session in progress',
                'session' => json_decode($sessionCookie)
            ]);
        }

        $domain = $request->domain ?: ( !empty($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : null );
        if( ! $domain ){
            return response([
                'error' => 'Domain required'
            ], 400);
        }

        //  
        //  Prep domain for parsing
        //  See: https://stackoverflow.com/questions/19597849/running-parse-url-on-a-string-that-may-not-contain-the-protocol
        //  
        if( ! preg_match('/^(http(s?))?:\/\//', $domain) )
            $domain = 'http://' . $domain;

        $domain = parse_url($domain, PHP_URL_HOST);
        if( ! $domain ){
            return response([
                'error' => 'Domain invalid'
            ], 400);
        }

        //  Determine IP, accounting for it being behind an ELB
        $ip = $request->header('X-Forwarded-For') ?: $request->ip();

        //  Prep the device specs
        $ua = !empty($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
        $dd = new DeviceDetector($ua);
        $dd->parse();

        //  Oh... No bots allowed
        if( $dd->isBot() ){
            return response([
                'error' => 'No bots allowed!'
            ], 409);
        }

        $client = $dd->getClient();
        $os     = $dd->getOs();

        $potentialDevice = new WebDevice();
        $potentialDevice->fill([
            'uuid'              => Str::uuid(),
            'ip'                => $ip,
            'width'             => intval($request->device_width) ?: null,
            'height'            => intval($request->device_height) ?: null,
            'type'              => substr($dd->getDeviceName(), 0, 128) ?: null,
            'brand'             => substr($dd->getBrandName(), 0, 128)  ?: null,
            'os'                => $os && ! empty($os['name']) ? substr($os['name'], 0, 128) : null,
            'os_version'        => $os && ! empty($os['version']) ? substr($os['version'], 0, 128) : null,
            'browser'           => $client && ! empty($client['name']) ? substr($client['name'], 0, 128) : null,
            'browser_version'   => $client && ! empty($client['version']) ? substr($client['version'], 0, 128) : null,
            'browser_engine'    => $client && ! empty($client['engine'])  ? substr($client['engine'], 0, 128)  : null,
        ]);

        $profileIdentity    = null;
        $profile            = null;
        $device             = null;
        $session            = null;
        $phoneNumber        = null;

        //  Has this user been identified either here or somewhere else??
        if( $profileIdentityUUID = $request->cookie('mkf_pi_uuid') ){
            //  Determine if this person has been identified by this website or another
            $profileIdentity = WebProfileIdentintity::where('uuid', $profileIdentityUUID)->first();
            if( $profileIdentity && $profile = WebProfile::find($profileIdentity->profile_id) ){
                //  Determine if we should keep this identity
                if( $profileIdentity->domain != $domain ){
                    //  They have been identified on another website
                    //  A new profile identity will have to be created based on this id and attached to the profile
                    $profileIdentity = null;
                }
                
                //  There should be a device attached
                $devices = WebDevice::where('profile_id', $profile->id)->get();
                if( count($devices) ){
                    //  First see if we can find a mdirect match based on device uuid
                    if( $deviceUUID = $request->cookie('mkf_device_uuid') ){
                        foreach( $devices as $d ){
                            if( $d->uuid == $deviceUUID ){
                                $device = $d;

                                break;
                            }
                        }
                    }
                    //  If for whatever reason we can't find this device from the cookie
                    //  see if we have one that matches specs
                    if( ! $device ){
                        $currentDeviceFingerprint = sha1();
                        foreach( $devices as $d ){
                            if( $d->getFingerprint() == $potentialDevice->getFingerprint() ){
                                $device = $d;

                                break;
                            }
                        }
                    }
                }
            }
        }

        //  Create a profile if none was found
        if( ! $profile ){
            $profile = WebProfile::create([
                'uuid' => Str::uuid()
            ]);
        }

        //  Create a profile identity if none was found
        if( ! $profileIdentity ){
            $profileIdentity = WebProfileIdentity::create([
                'uuid'           => Str::uuid(),
                'web_profile_id' => $profile->id,
                'domain'         => $domain
            ]);
        }

        //  Create a device if none was found
        if( ! $device ){
            $device                             = $potentialDevice;
            $potentialDevice->fingerprint       = $potentialDevice->getFingerprint(); 
            $potentialDevice->web_profile_id    = $profile->id;
            $potentialDevice->save();
        }

        //  Create a session
        $campaign    = null;
        $phoneNumber = null;
        if( $campaignUUID = $request->campaign_uuid ){
            $campaign = Campaign::where('uuid', $campaignUUID)->first();
            if( $campaign && $campaign->type == Campaign::TYPE_WEB && $campaign->active() ){
                $campaignDomain = CampaignDomain::where('campaign_id', $campaign->id)
                                                 ->where('domain', $domain)
                                                 ->first();
                                                 
                $phoneNumberPool = PhoneNumberPool::find($campaign->id);

                $phoneNumber = $phoneNumberPool ? $phoneNumberPool->assignPhone() : null;
            }
        }

        $session = WebSession::create([
            'uuid'                      => Str::uuid(),
            'web_profile_identity_id'   => $profileIdentity->id,
            'web_device_id'             => $device->id,
            'campaign_id'               => $campaign ? $campaign->id : null,
            'phone_number_id'           => $phoneNumber ? $phoneNumber->id : null
        ]);

        $session->device       = $device;
        $session->campaign     = $campaign;
        $session->phone_number = $phoneNumber;

        $cookieYearsInMinutes = 60 * 24 * 365 * 99; // Store cookie for 99 years...
        $cookieDomain         = env('COOKIE_DOMAIN');
        $sessionCookieTime    = 0;

        return response([
            'session' => $session,
            'message' => 'created'
        ], 201)->withCookie(cookie('mkf_session', json_encode($session), 0, '/', $cookieDomain))
               ->withCookie(cookie('mkf_pi_uuid', $profileIdentity->uuid, $cookieYearsInMinutes, '/', $cookieDomain))
               ->withCookie(cookie('mkf_device_uuid', $device->uuid, $cookieYearsInMinutes, '/', $cookieDomain));
    }
}
