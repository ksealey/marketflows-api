<?php

namespace App\Http\Controllers\Events;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Company\PhoneNumber;
use App\Models\Company\PhoneNumberPool;
use App\Models\Events\SessionProfile;
use App\Models\Events\Session;
use App\Models\Events\SessionEvent;
use DeviceDetector\DeviceDetector;
use Validator;

class SessionController extends Controller
{
    /**
     * Start a new session
     * 
     */
    public function start(Request $request)
    {
        $validator = Validator::make($request->input(), [
            'persisted_id'          => 'uuid',
            'company_id'            => 'bail|required|exists:companies,id',
            'entry_url'             => 'bail|required',
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

        //
        //  Make sure there is an active number pool for this company
        //
        $pool = PhoneNumberPool::where('company_id', $request->company_id)
                               ->first();

        if( ! $pool || $pool->disabled_at )
            return response([
                'error'       => 'No pool found for company ' . $request->company_id,
                'swapping'    => [
                    'should_swap'   => false
                    'number'        => null
                    'targets'       => [],
                ],
                'session' => null,
            ], 400);
        
        //
        //  Make sure a swap should occur
        //
        if( ! $pool->shouldSwap($request->entry_url) ){
            return response([
                'swapping'    => [
                    'should_swap'   => false
                    'number'        => null
                    'targets'       => [],
                ],
                'session' => null,
            ]);
        }

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
        $targets     = $pool->targets(); 

        //  Create new session
        $session = Session::create([
            'persisted_id'      => $request->persisted_id ?: Str::uuid(),
            'company_id'        => $request->company_id,
            'phone_number_id'   => $phoneNumber ? $phoneNumber->id : null,
            'first_session'     => Session::where('persisted_id', $request->persisted_id)->count() ? false : true,
            'ip'                => $ip,
            'device_width'      => $request->device_width,
            'device_height'     => $request->device_height,
            'device_type'       => $deviceType,
            'device_brand'      => $deviceBrand,
            'device_os'         => $deviceOS,
            'browser_type'      => $deviceBrowser,
            'browser_version'   => $deviceBrowserVersion,
            'token'             => str_random(40),
            'started_at'        => now()
        ]);

        $event = SessionEvent::create([
            'session_id' => $session->id,
            'event_type' => 'StartSession',
            'created_at' => now()   
        ]);

        //  Return the number and targets
        return response([
            'swapping'    => [
                'should_swap'   => false
                'number'        => [
                    'id'            => $phoneNumber->id,
                    'country_code'  => $phoneNumber->country_code,
                    'number'        => $phoneNumber->number
                ],
                'targets'       => $targets,
            ],
            'session'     => $session
        ], 201);
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
