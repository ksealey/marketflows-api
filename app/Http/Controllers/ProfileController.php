<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Rules\UniqueEmailRule;
use App\Models\Auth\EmailVerification;
use \App\Models\User;
use \Carbon\Carbon;
use Mail;

class ProfileController extends Controller
{
    public function me(Request $request)
    {
        return response($request->user());
    }

    public function updateMe(Request $request)
    {
        $me    = $request->user();
        $rules = [
            'timezone'   => 'timezone',
            'first_name' => 'min:1',
            'last_name'  => 'min:1',
            'phone'      => 'numeric:digits_between:10,13'
        ];

        $validator = validator($request->input(), $rules);
        if($validator->fails()){
            return response([
                'error' => $validator->errors()->first()
            ], 400);
        }

        if( $request->filled('timezone') )
            $me->timezone = $request->timezone;

        if( $request->filled('first_name') )
            $me->first_name = $request->first_name;

        if( $request->filled('last_name') )
            $me->last_name = $request->last_name;

        if( $request->has('phone') )
            $me->phone = $request->phone;

        $me->save();

        return response($me);
    }

    public function updateEmail(Request $request)
    {
        $validator = validator($request->input(), [
            'email' => ['bail', 'required', 'email', 'max:128', new UniqueEmailRule(null)],
        ]);

        if( $validator->fails() ){
            return response([
                'error' => $validator->errors()->first()
            ], 400);
        }
        
        //  Make sure the email was verified
        $emailVerification = EmailVerification::where('email', $request->email)
                                              ->whereNotNull('verified_at')
                                              ->first();
        if( ! $emailVerification ){
            return response([
                'error' => 'You must verify this email address before updating'
            ], 400);
        }

        $emailVerification->delete();

        $me        = $request->user();
        $me->email = $request->email;
        $me->save();

        return response($me);
    }
}
