<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request; 
use Illuminate\Support\Str;
use Validator;

class SessionController extends Controller
{
    public function create(Request $request)
    {
        //  Do not allow sessions for bots


        //  Determine if we should log a new person
        if( ! $personId = $request->person )
            $personId = Str::uuid()->toString();

        $sessionId =  Str::uuid()->toString();

        //  Determine IP
        $ip = $request->header('X-Forwarded-For') ?: $request->ip();

        //  Determine referrer
        $referrer = $request->server('HTTP_REFERER') ?: $request->referrer;

        //  Determine Device
        $dd = 

        //  Determine OS

        //  Determine Browser

        //  Determine Browser Version

        //  Send to insights

    

        
    }



}
