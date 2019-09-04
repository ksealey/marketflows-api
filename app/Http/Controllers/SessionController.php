<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request; 
use Illuminate\Support\Str;
use DeviceDetector\DeviceDetector;
use App\Helpers\InsightsClient;
use App\Models\Company\PhoneNumberPool;
use App\Models\Company\Campaign;

class SessionController extends Controller
{
    public function create(Request $request)
    {
        $insights = new InsightsClient();

        //  Get an override session id (Unique uuid)
        $sessionId = str_random(64);

        //  Determine IP
        $ip = $request->header('X-Forwarded-For') ?: $request->ip();

        //  Determine Device Specs
        $dd = new DeviceDetector(!empty($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '');
        $dd->parse();

        $client = $dd->getClient();
        $os     = $dd->getOs();

        $deviceType             = $dd->getDeviceName() ?: null;
        $deviceBrand            = $dd->getBrandName()  ?:null;      
        $deviceOS               = $os && ! empty($os['name'])               ? $os['name']        : null;
        $deviceOSVersion        = $os && ! empty($os['version'])            ? $os['version']     : null;
        $deviceBrowser          = $client && ! empty($client['name'])       ? $client['name']    : null;
        $deviceBrowserVersion   = $client && ! empty($client['version'])    ? $client['version'] : null;
        $deviceBrowserEngine    = $client && ! empty($client['engine'])     ? $client['engine'] : null;


        $isBot       = $dd->isBot();
        $campaignId  = intval($request->campaign_id) ?: null;
        if( ! $isBot 
            && $campaignId 
            && $campaign = Campaign::find($campaignId) 
            && $campaign->type == Campaign::TYPE_WEB
            && $campaign->active() 
        ){  
            //
            //  We have an active campaign for a valid user....
            //
            $phoneNumberPool = PhoneNumberPool::find($campaign->id);

            $phoneNumber     = $phoneNumberPool ? $phoneNumberPool->assignPhone($sessionId) : null;
        }else{
            $phoneNumber = null;
        }
        
        //  Send to insights to create a session
        $session = $insights->session([
            'sessionId'             => $sessionId,
            'entityId'              => $request->entity_id,
            'ip'                    => $ip,
            'deviceWidth'           => intval($request->device_width) ?: null,
            'deviceHeight'          => intval($request->device_height) ?: null,
            'deviceType'            => $deviceType,
            'deviceBrand'           => $deviceBrand,
            'deviceOS'              => $deviceOS,
            'deviceOSVersion'       => $deviceOSVersion,
            'deviceBrowser'         => $deviceBrowser,
            'deviceBrowserVersion'  => $deviceBrowserVersion,
            'isBot'                 => $isBot,
            'campaignId'            => $campaignId,
            'phoneNumber'           => $phoneNumber ? $phoneNumber->phoneNumber() : null,
        ]);

        //  Return to client
        return response([
            'session' => $session
        ]);
    }



}
