<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Mail\Auth\EmailVerification as UserEmailVerificationMail;
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
            'email'      => [
                'email',
                'max:128',
                Rule::unique('users')->where(function ($query) use($me){
                    $query->where('account_id', '!=', $me->account_id);
                })
            ],
            'phone' => 'nullable|digits_between:10,13'
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
  
        if( $request->filled('email') && $request->email != $me->email ){
            $me->email             = $request->email;
            $me->email_verified_at = null;
            Mail::to($me)->send(new UserEmailVerificationMail($me));
        }

        if( $request->has('phone') && $request->phone != $me->phone ){
            $me->phone = preg_replace('/[^0-9]+/', '', $request->phone) ?: null;
            $me->phone_verified_at = null;
        }

        $me->save();

        return response($me);
    }

    public function resendVerificationEmail(Request $request)
    {
        $me = $request->user();
        
        Mail::to($me)->send(new UserEmailVerificationMail($me));

        return response([
            'message' => 'sent'
        ]);
    }
}
