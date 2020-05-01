<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Auth\PasswordReset;
use App\Mail\Auth\PasswordReset as PasswordResetEmail;
use Validator;
use DateTime;
use Mail;

class LoginController extends Controller
{
    /**
     * Log a user in
     * 
     */
    public function login(Request $request)
    {
        $rules = [
            'email'    => 'bail|required|email',
            'password' => 'bail|required'
        ];

        $validator = validator($request->input(), $rules);
        if( $validator->fails() ){
            return response([
                'error' => $validator->errors()->first()
            ], 400);
        }

        $user = User::where('email', $request->email)->first();
        if( ! $user ){
            return response([
                'error' => 'User does not exist'
            ], 400);
        }

        //  Block disabled users
        if( $user->login_disabled_until && date('U', strtotime($user->login_disabled_until)) > date('U')){
            $now           = new DateTime();
            $disabledUntil = new DateTime($user->login_disabled_until);
            $hours         = ceil(($disabledUntil->format('U') - $now->format('U')) / 3600);
            
            return response([
                'error' => 'Account disabled for the next ' . $hours . ' hours - try again later'
            ], 400);
        }
        
        if( ! password_verify( $request->password, $user->password_hash) ){
            $user->login_attempts++;

            //  If we have another failed attempt, lock for a longer period
            if( $user->login_attempts > 3 ){
                $user->login_disabled_until = date('Y-m-d H:i:s', strtotime('now +' . $user->login_attempts . ' hours'));
                $user->save();

                return response([
                    'error' => 'Too many failed attempts - account disabled for ' . $user->login_attempts . ' hours',
                ], 400);
            }else{
                $user->save();

                return response([
                    'error' => 'Invalid credentials'
                ], 400);
            }
        }

        $user->login_attempts            = 0;
        $user->login_disabled_until      = null;
        $user->last_login_at             = now();
        $user->auth_token                = str_random(255);
        $user->password_reset_token      = null;
        $user->password_reset_expires_at = null;
        
        $user->save();

        return response([
            'message'       => 'created',
            'auth_token'    => $user->auth_token,
            'user'          => $user,
            'account'       => $user->account,
            'first_login'   => false
        ], 200);
    }

    /**
     * Request a password reset
     * 
     * @param Illuminate\Http\Request $request
     * 
     * @return Illuminate\Http\Response  
     */
    public function requestResetPassword(Request $request)
    {
        $validator = validator($request->input(),  [
            'email' => 'required|email|max:128|exists:users,email',
        ]);

        if( $validator->fails() ){
            return response([
                'error' => $validator->errors()->first(),
            ], 400);
        }

        //  Look for user requesting password
        $user = User::where('email', $request->email)->first();

        $user->password_reset_token      = str_random(128);
        $user->password_reset_expires_at = now()->addHours(24);
        $user->save();

        Mail::to($user)
            ->later(now(), new PasswordResetEmail($user));

        return response([
            'message' => 'sent'
        ]);
    }

    /**
     * Reset the password
     * 
     * @param Illuminate\Http\Request $request
     * @param int $userId
     * @param string $key 
     */
    public function resetPassword(Request $request)
    {
        $rules = [
            'user_id'  => 'required|exists:users,id',
            'token'    => 'required|min:128',
            'password' => [
                'bail',
                'required',
                'min:8',
                'regex:/(?=.*[0-9])(?=.*[A-Z])/'
            ],
        ];
        
        $validator = validator($request->input(), $rules);
        if( $validator->fails() )
            return response([
                'error' => $validator->errors()->first()
            ], 400);
        
        $user = User::find($request->user_id);
        if( $user->password_reset_token !== $request->token ){
            return response([
                'error' => 'invalid token'
            ], 400);
        }
        if( strtotime($user->password_reset_expires_at) <= strtotime('now') ){
            return response([
                'error' => 'token expired'
            ], 400);
        }

        $user->password_hash             = bcrypt($request->password);
        $user->password_reset_token      = null;
        $user->password_reset_expires_at = null;
        $user->login_attempts            = 0;
        $user->login_disabled_until      = null;
        $user->auth_token                = str_random(128);
        $user->save();

        return response([
            'message'       => 'reset',
            'auth_token'    => $user->auth_token,
            'user'          => $user,
            'account'       => $user->account,
            'first_login'   => false
        ], 200);
    }

    /**
     * Check is a password reset is valid or not
     * 
     * @param Illuminate\Http\Request $request
     * @param int $userId
     * @param string $key 
     */
    public function checkResetPassword(Request $request)
    {
        $rules = [
            'user_id'  => 'required|exists:users,id', 
            'token'    => 'required|min:128',
        ];
        
        $validator = validator($request->input(), $rules);
        if( $validator->fails() )
            return response([
                'error' => $validator->errors()->first()
            ], 400);

        $user = User::find($request->user_id);
        if( $user->password_reset_token !== $request->token ){
            return response([
                'error' => 'invalid token'
            ], 400);
        }
        
        return response([
            'message' => 'exists'
        ]);
    }
}