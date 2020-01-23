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
            'assign_number'         => 'bail|boolean'
        ]);

        if( $validator->fails() ){
            return response([
                'error' => $validator->errors()->first()
            ], 400);
        }
         
        //  
        //  Reject robots
        //
        $dd = new DeviceDetector(!empty($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '');
        $dd->parse();
        if( $dd->isBot() ){
            return response([
                'error' => 'Robots not allowed'
            ], 400);
        }

        //  Determine Device Specs
        $os     = $dd->getOs();
        $client = $dd->getClient();

        $deviceType             = $dd->getDeviceName() ?: null;
        $deviceBrand            = $dd->getBrandName()  ?:null;      
        $deviceOS               = $os && ! empty($os['name'])            ? substr($os['name'], 0, 64)        : null;
        $deviceOSVersion        = $os && ! empty($os['version'])         ? substr($os['version'], 0, 64)     : null;
        $deviceBrowser          = $client && ! empty($client['name'])    ? substr($client['name'], 0, 64)    : null;
        $deviceBrowserVersion   = $client && ! empty($client['version']) ? substr($client['version'], 0, 64) : null;
        $deviceBrowserEngine    = $client && ! empty($client['engine'])  ? substr($client['engine'], 0, 64)  : null;

        //  Determine IP
        $ip = $request->header('X-Forwarded-For') ?: $request->ip();

        //  Check if we have an active pool
        $pool = PhoneNumberPool::where('company_id', $request->company_id)
                                    ->where('category', 'ONLINE')
                                    ->where('sub_category', 'WEBSITE_SESSION')
                                    ->first();

        $previousSession = null;
        if( $request->persisted_id ){
            //  End existing sessions for this user if never ended
            $previousSession = Session::where('persisted_id',  $request->persisted_id)
                                        ->orderBy('created_at', 'DESC')
                                        ->first();
            if( $previousSession && ! $previousSession->ended_at ){
                $previousSession->ended_at = now();
                $previousSession->save();
                
                //  Generate event
                SessionEvent::create([
                    'session_id' => $previousSession->id,
                    'event_type' => 'EndSession',
                    'is_public'  => true,
                    'created_at' => now()  
                ]);
            }
        }

        $phoneNumber = null;
        $targets     = [];
        if( $pool && $pool->shouldSwap($request->entry_url) ){
            //  Use the same phone as before if available
            $phoneNumber = $pool->assignPhoneNumber($previousSession ? $previousSession->phone_number_id : null);
            $targets     = $pool->targets(); 
        }else{
            //  If there is no session pool, check if a number should do the swapping
            $phoneNumbers = PhoneNumber::where('company_id', $request->company_id)
                                        ->where('category', 'ONLINE')
                                        ->where('sub_category', 'WEBSITE')
                                        ->orderBy('created_at', 'desc')
                                        ->get();
            //  Get the most recent number that has passing swap rules
            foreach( $phoneNumbers as $p ){
                if( $p->shouldSwap($request->entry_url) ){
                    $phoneNumber = $p;
                    $targets     = $p->targets(); 

                    break;
                }
            }
        }

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
            'is_public'  => true,
            'created_at' => now()   
        ]);

        //  Return the number and targets
        return response([
            'session' => $session,
            'number' => $phoneNumber ? [
                'id'            => $phoneNumber->id,
                'country_code'  => $phoneNumber->country_code,
                'number'        => $phoneNumber->number
            ] : null,
            'targets'         => $targets,
            'session_token'   => $session->token,
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
            'is_public'  => true,
            'created_at' => now()  
        ]);

        return response([
            'message' => 'Session Ended'
        ]);
    }
}
