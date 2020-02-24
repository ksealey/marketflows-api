<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Company\PhoneNumber;
use App\Models\Company\PhoneNumberPool;
use App\Models\Events\Session;
use App\Models\Events\SessionEvent;
use DeviceDetector\DeviceDetector;
use Validator;

class SessionController extends Controller
{
    /**
     *  Take in client information and return the swap object 
     * 
     */
    public function create(Request $request)
    {
        $validator = Validator::make($request->input(), [
            'persisted_id'          => 'uuid',
            'company_id'            => 'bail|required|exists:companies,id',
            'http_referrer'         => 'bail|url',
            'entry_url'             => 'bail|required|url',
            'device_width'          => 'bail|required|numeric',
            'device_height'         => 'bail|required|numeric',
        ]);

        if( $validator->fails() )
            return response([
                'error' => $validator->errors()->first()
            ], 400);

        //  
        //  Reject robots
        //
        $dd = new DeviceDetector(!empty($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '');
        $dd->parse();
        if( $dd->isBot() )
            return response([
                'error' => 'Robots not allowed'
            ], 403);

        //
        //  End existing sessions for this user if it never ended
        //  We will re-use this number for this user if it's still available in the pool
        //
        $previousSession = null;
        if( $request->persisted_id ){
            $previousSession = Session::where('persisted_id',  $request->persisted_id)
                                       ->orderBy('created_at', 'DESC')
                                       ->first();

            if( $previousSession && ! $previousSession->ended_at ){
                $previousSession->ended_at = now();
                $previousSession->save();
                
                //  Generate end session event
                SessionEvent::create([
                    'session_id' => $previousSession->id,
                    'event_type' => 'EndSession',
                    'created_at' => now()  
                ]);
            }
        }

        $noSwapResponse = [
            'swapping' => [
                'should_swap' => false,
                'targets'     => [],
                'number'      => null
            ],
            'session'   => null,
        ];

        //
        //  
        //  Make sure there is an active number pool for this company
        //
        $pool = PhoneNumberPool::where('company_id', $request->company_id)
                               ->first();

        $campaignPhoneNumber = null;
        $phoneNumbers        = PhoneNumber::where('company_id', $request->company_id)
                                          ->where('category', 'ONLINE')
                                          ->where('sub_category', 'WEBSITE')
                                          ->whereNull('phone_number_pool_id')
                                          ->orderBy('created_at', 'DESC')
                                          ->get();

        foreach( $phoneNumbers as $phoneNumber ){
            if( $phoneNumber->shouldSwap($request->entry_url, $request->http_referrer) ){
                $campaignPhoneNumber = $phoneNumber;
                
                break;
            }
        }

        //  If our only option is the campaign number ...
        if( ! $pool || $pool->disabled_at || ! $pool->shouldSwap($request->entry_url, $request->http_referrer) ){
            if( $campaignPhoneNumber ){
                return response([
                    'swapping' => [
                        'should_swap'   => true,
                        'targets'       => $campaignPhoneNumber->targets(),
                        'number'        => $campaignPhoneNumber->exposedData()
                    ],
                    'session' => null
                ]);
            }

            //  Nothing we can do
            return response($noSwapResponse);
        }

        //
        //  The pool is valid and should swap
        //

        //  If the pool is not set to override and there is a campaign number, use the campaign number
        if( $campaignPhoneNumber && ! $pool->override_campaign ){
            return response([
                'swapping' => [
                    'should_swap'   => true,
                    'targets'       => $campaignPhoneNumber->targets(),
                    'number'        => $campaignPhoneNumber->exposedData()
                ],
                'session' => null
            ]);
        }

        //
        //  We're using the pool, so create a session
        //

        //
        //  Determine Device Specs
        //
        $os     = $dd->getOs();
        $client = $dd->getClient();

        $deviceType             = $dd->getDeviceName() ?: null;
        $deviceBrand            = $dd->getBrandName()  ?:null;      
        $deviceOS               = $os && ! empty($os['name'])            ? substr($os['name'], 0, 64)        : null;
        $deviceOSVersion        = $os && ! empty($os['version'])         ? substr($os['version'], 0, 64)     : null;
        $deviceBrowser          = $client && ! empty($client['name'])    ? substr($client['name'], 0, 64)    : null;
        $deviceBrowserVersion   = $client && ! empty($client['version']) ? substr($client['version'], 0, 64) : null;
        $deviceBrowserEngine    = $client && ! empty($client['engine'])  ? substr($client['engine'], 0, 64)  : null;

        //  Determine IP through load balancer
        $ip = $request->header('X-Forwarded-For') ?: $request->ip();

        //  Find next available number
        $phoneNumber = $pool->assignNextNumber($previousSession ? $previousSession->phone_number_id : null); 

        //  Create new session
        $session = Session::create([
            'persisted_id'      => $request->persisted_id ?: Str::uuid(),
            'company_id'        => $request->company_id,
            'phone_number_id'   => $phoneNumber->id,
            'first_session'     => $previousSession ? false : true,
            'ip'                => $ip,
            'host'              => substr(parse_url($request->entry_url, PHP_URL_HOST), 0, 128),
            'device_width'      => intval($request->device_width),
            'device_height'     => intval($request->device_height),
            'device_type'       => $deviceType,
            'device_brand'      => $deviceBrand,
            'device_os'         => $deviceOS,
            'browser_type'      => $deviceBrowser,
            'browser_version'   => $deviceBrowserVersion,
            'token'             => str_random(40),
            'started_at'        => now()
        ]);

        //  Log start session event
        $event = SessionEvent::create([
            'session_id' => $session->id,
            'event_type' => 'StartSession',
            'created_at' => now()   
        ]);

        //  Return the number and targets
        return response([
            'swapping' => [
                'should_swap'   => true,
                'targets'       => $pool->targets(),
                'number'        => $phoneNumber->exposedData()
            ],
            'session' => $session
        ]);
    }


    /**
     * Create a new session event
     * 
     */
    public function event(Request $request)
    {
        $validator = Validator::make($request->input(), [
            'session_id'    => 'required|uuid',
            'session_token' => 'required|string|size:40',
            'event_type'    => 'required|in:PageView,ClickToCall,PageClosed',
            'content'       => 'string',
        ]);

        if( $validator->fails() ){
            return response([
                'error' => $validator->errors()->first()
            ], 400);
        }

        $session = Session::find($request->session_id);
        if( ! $session || $session->token !== $request->session_token )
            return response([
                'error' => 'Invalid session'
            ], 400);

        $event = SessionEvent::create([
            'session_id' => $session->id,
            'event_type' => $request->event_type,
            'content'    => substr($request->content, 0, 512),
            'created_at' => now()
        ]);

        return response('Accepted', 202);
    }

    /**
     * End an existing session
     * 
     */
    public function end(Request $request)
    {
        $validator = Validator::make($request->input(), [
            'session_id'    => 'required|uuid',
            'session_token' => 'required|min:40'
        ]);

        if( $validator->fails() ){
            return response([
                'error' => $validator->errors()->first()
            ], 400);
        }

        $session = Session::find($request->session_id);
        if( ! $session || $session->token != $request->session_token )
            return response([
                'error' => 'Invalid token'
            ], 400);
       
        $session->ended_at = now();
        $session->save();

        SessionEvent::create([
            'session_id' => $session->id,
            'event_type' => 'EndSession',
            'created_at' => now()  
        ]);

        return response([
            'message' => 'Session Ended'
        ]);
    }
}
