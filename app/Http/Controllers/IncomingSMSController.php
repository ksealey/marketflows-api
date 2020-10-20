<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Twilio\TwiML\VoiceResponse;

class IncomingSMSController extends Controller
{
    public function handleSMS(Request $request)
    {
        return Response::xmlResponse(new VoiceResponse);
    }

    public function handleMMS(Request $request)
    {
        return Response::xmlResponse(new VoiceResponse);
    }
}
