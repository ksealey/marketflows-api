<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use DeviceDetector\DeviceDetector;
use App\Models\Company;
use App\Models\Company\PhoneNumber;
use App\Models\Company\PhoneNumberPool;

class OnlineController extends Controller
{
    /**
     * Entry point for all web sessions
     * 
     */
    public function init(Request $request)
    {
        $rules = [
            'company_id'            => 'bail|required|numeric',
            'persisted_id'          => 'uuid',
            'http_referrer'         => 'bail|url',
            'entry_url'             => 'bail|required|url',
            'device_width'          => 'bail|required|numeric',
            'device_height'         => 'bail|required|numeric',
        ];

        $validator = validator($request->input(), $rules);
        if( $validator->fails() ){
            return response([
                'error' => $validator->errors()->first()
            ], 400);
        }

        //  
        //  Reject robots and determine device specs
        //
        $dd = new DeviceDetector($request->header('User-Agent'));
        $dd->parse();
        if( $dd->isBot() )
            return response([
                'error' => 'Robots not allowed'
            ], 403); 

        $os     = $dd->getOs();
        $client = $dd->getClient();

        $deviceType             = $dd->getDeviceName() ?: null;
        $deviceBrand            = $dd->getBrandName()  ?:null;      
        $deviceOS               = $os && ! empty($os['name'])            ? substr($os['name'], 0, 64)        : null;
        $deviceOSVersion        = $os && ! empty($os['version'])         ? substr($os['version'], 0, 64)     : null;
        $deviceBrowser          = $client && ! empty($client['name'])    ? substr($client['name'], 0, 64)    : null;
        $deviceBrowserVersion   = $client && ! empty($client['version']) ? substr($client['version'], 0, 64) : null;
        $deviceBrowserEngine    = $client && ! empty($client['engine'])  ? substr($client['engine'], 0, 64)  : null;

        //
        //  Make sure the company exists
        //
        $company = Company::find($request->company_id);
        if( ! $company ){
            return response([
                'error' => 'Company not found'
            ], 404);
        }

        //
        //  If the user has an online pool that passes rules, it will take precedence
        //
        $ipAddress      = $request->header('X-Forwarded-For') ?: $request->ip();
        $persistedId    = $request->persisted_id ?: Str::uuid();
        $assignedNumber = null;

        $pool = PhoneNumberPool::where('company_id', $request->company_id)->first();
        if( $pool && $pool->swapRulesPass($deviceBrowser, $deviceType, $request->http_referrer, $request->entry_url) ){
            //  Get next available number in pool
            $assignedNumber = $pool->assignNumber($request->persisted_id);

            if( $assignedNumber ){
                //  Create session
                $session = null;

                //  Log session start event
                //  ...

                return response([
                    'action' => 'session',
                    'data'   => [
                        'number'       => $assignedNumber->exposedData(),
                        'swap_rules'   => $pool->swap_rules,
                        'persisted_id' => $persistedId,
                        'session'      => $session,
                    ],
                ]);
            }
        }else{
            //  See if we have a campaign number that should swap numbers
            $numbers = PhoneNumber::where('company_id', $company->id)
                                  ->orderBy('created_at', 'desc')
                                  ->get();

            foreach( $numbers as $number ){
                if( $number->swapRulesPass($deviceBrowser, $deviceType, $request->http_referrer, $request->entry_url) ){
                    if( $assignedNumber ){
                        //  User the number with the hisghest score we encounter more than one that matches
                        //  When they have the same amount of rules, pick the number that's newer
                        if( $number->swapScore() > $assignedNumber->swapScore() ){
                            $assignedNumber = $number;
                        }
                    }else{
                        $assignedNumber = $number;
                    }
                }
            }

            if( $assignedNumber ){
                return response([
                    'action' => 'swap',
                    'data'   => [
                        'number'       => $assignedNumber->exposedData(),
                        'swap_rules'   => $assignedNumber->swap_rules,
                        'persisted_id' => $persistedId,
                    ],
                ]);
            }
        }

        // 
        //  Create no-action response
        //
        return response([
            'action' => 'none',
            'data'   => null
        ], 200);
    }

    /**
     * Log events
     * 
     */
    public function event()
    {
        
    }

    /**
     * Keep a session alive
     * 
     */
    public function heartbeat()
    {
        $rules = [
            'session_id' => 'required|uuid'
        ];

        return response([
            'message' => 'I love you too'
        ]);
    }
}
