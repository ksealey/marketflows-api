<?php

namespace App\Http\Controllers\Events;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Events\Session;
use App\Models\Events\SessionEvent;
use Validator;

class SessionEventController extends Controller
{
    /**
     * Create a new session event
     * 
     */
    public function create(Request $request)
    {
        $validator = Validator::make($request->input(), [
            'session_id'    => 'required|uuid',
            'session_token' => 'required|string|size:40',
            'event_type'    => 'required|in:SessionStart,PageView,ClickToCall',
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

        return response([
            'message' => 'Accepted'
        ], 202);
    }
}
