<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use DeviceDetector\DeviceDetector;
use App\Models\Company;
use App\Models\Company\PhoneNumber;
use App\Models\Company\PhoneNumberPool;
use App\Models\TrackingEntity;
use App\Models\TrackingSession;
use App\Models\TrackingSessionEvent;

use DateTime;

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
            'tracking_entity_uuid'  => 'nullable|uuid',
            'http_referrer'         => 'bail|nullable|url',
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
        $fingerprint    = hash('sha256', $company->id . ($request->fingerprint ?: ($ipAddress . $deviceType . $deviceBrand . $deviceOS . $deviceBrowser)));
        $assignedNumber = null;

        $pool   = PhoneNumberPool::where('company_id', $request->company_id)->first();
        $entity = null;

        if( $pool && ! $pool->disabled_at && $pool->swapRulesPass($deviceBrowser, $deviceType, $request->http_referrer, $request->entry_url) ){
            //  Look for entity bu uuid
            if( $request->tracking_entity_uuid ){
                $entity = TrackingEntity::where('uuid', $request->tracking_entity_uuid)
                                        ->where('company_id', $company->id)
                                        ->first();
            }

            //  Look for entity by fingerprint
            if( ! $entity ){
                $entity = TrackingEntity::where('fingerprint', $fingerprint)
                                        ->where('company_id', $company->id)
                                        ->first();
            }

            //
            //  Get next available number in pool, re-using the same number if possible
            //
            $assignedNumber = $pool->assignNumber($entity);

            //
            //  If we can get assigned a number
            //
            if( $assignedNumber ){
                //
                //  Create a new entity if not exists
                //
                if( ! $entity ){
                    $entity = TrackingEntity::create([
                        'account_id' => $company->account_id,
                        'company_id' => $company->id,
                        'uuid'       => Str::uuid(),
                        'fingerprint'=> $fingerprint
                    ]);
                }
                
                //
                //  Parse entry params and normalize keys
                //
                parse_str(parse_url($request->entry_url, PHP_URL_QUERY), $_params);
                $params = [];
                foreach( $_params as $paramKey => $paramValue ){
                    $params[strtolower($paramKey)] = $_params[$paramKey];
                }

                //
                //  Create session
                //
                $now     = new DateTime();
                $session = TrackingSession::create([
                    'uuid'                  => Str::uuid(),
                    'tracking_entity_id'    => $entity->id,
                    'company_id'            => $company->id,
                    'phone_number_pool_id'  => $pool->id,
                    'phone_number_id'       => $assignedNumber->id,
                    'ip'                    => $ipAddress,
                    'host'                  => substr(parse_url($request->entry_url, PHP_URL_HOST), 0, 128),
                    'device_width'          => $request->device_width,
                    'device_height'         => $request->device_height,
                    'device_type'           => substr($deviceType, 0, 64),
                    'device_brand'          => substr($deviceBrand, 0, 64),
                    'device_os'             => substr($deviceOS, 0, 64),
                    'browser_type'          => substr($deviceBrowser, 0, 64),
                    'browser_version'       => substr($deviceBrowserVersion, 0, 64),
                    'source'                => substr($params['utm_source']   ?? $params['source']   ?? '', 0, 128) ?: null,
                    'medium'                => substr($params['utm_medium']   ?? $params['medium']   ?? '', 0, 128) ?: null,
                    'content'               => substr($params['utm_content']  ?? $params['content']  ?? '', 0, 128) ?: null,
                    'campaign'              => substr($params['utm_campaign'] ?? $params['campaign'] ?? '', 0, 128) ?: null,
                    'keyword'               => substr($params['keyword']      ?? $params['keyword']  ?? '', 0, 128) ?: null,
                    'token'                 => str_random(40),
                    'created_at'            => $now->format('Y-m-d H:i:s.u'),
                    'updated_at'            => $now->format('Y-m-d H:i:s.u'),
                    'last_heartbeat_at'     => $now->format('Y-m-d H:i:s.u')
                ]);

                //  Log session start event
                TrackingSessionEvent::create([
                    'tracking_session_id' => $session->id,
                    'event_type'          => TrackingSessionEvent::SESSION_START,
                    'created_at'          => $now->format('Y-m-d H:i:s.u')
                ]);

                //  Log page view event
                TrackingSessionEvent::create([
                    'tracking_session_id' => $session->id,
                    'event_type'          => TrackingSessionEvent::PAGE_VIEW,
                    'content'             => substr($request->entry_url, 0, 1024),
                    'created_at'          => $now->format('Y-m-d H:i:s.u')
                ]);

                //  Return to user
                return response([
                    'action' => 'session',
                    'data'   => [
                        'tracking_entity_uuid' => $entity->uuid,
                        'number'               => $assignedNumber->exposedData(),
                        'swap_rules'           => [
                            'targets' => $pool->swap_rules->targets
                        ],
                        'session'      => [
                            'uuid'  => $session->uuid,
                            'token' => $session->token,
                            'created_at' => $session->created_at,
                        ],
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
                        'swap_rules'           => [
                            'targets' => $assignedNumber->swap_rules->targets
                        ],
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
    public function event(Request $request)
    {
        $rules = [
            'event_type'   => 'bail|required|in:PageView,ClickToCall',
            'session_uuid' => 'bail|required|uuid',
            'token'        => 'bail|required|size:40'
        ];
        
        $validator = validator($request->input(), $rules);
        if( $validator->fails() ){
            return response([
                'error' => $validator->errors()->first()
            ], 400);
        }

        $session = TrackingSession::where('uuid', $request->session_uuid)
                                  ->where('token', $request->token)
                                  ->first();
        if( ! $session ){
            return response([
                'error' => 'Session not found'
            ], 404);
        }

        $now = new DateTime();
        TrackingSessionEvent::create([
            'tracking_session_id' => $session->id,
            'event_type'          => $request->event_type,
            'content'             => substr($request->content ?: '', 0, 1024) ?: null,
            'created_at'          => $now->format('Y-m-d H:i:s.u')
        ]);

        $session->ended_at          = null; //  Open back up session if ended
        $session->last_heartbeat_at = $now->format('Y-m-d H:i:s.u');

        $session->save();

        return response([
            'message' => 'created'
        ], 201);
    }

    /**
     * Keep a session alive
     * 
     */
    public function heartbeat(Request $request)
    {
        $rules = [
            'session_uuid' => 'bail|required|uuid',
            'token'        => 'bail|required|size:40'
        ];
        
        $validator = validator($request->input(), $rules);
        if( $validator->fails() ){
            return response([
                'error' => $validator->errors()->first()
            ], 400);
        }

        $session = TrackingSession::where('uuid', $request->session_uuid)
                                    ->where('token', $request->token)
                                    ->first();
        if( ! $session ){
            return response([
                'error' => 'Session not found.',
                'message'=> 'You\'re dead to me.'
            ], 404);
        }

        $session->last_heartbeat_at = (new DateTime())->format('Y-m-d H:i:s.u');
        $session->ended_at = null; //  Open back up session if ended
        $session->save();

        return response([
            'message' => 'I love you too'
        ]);
    }
}
