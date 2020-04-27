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

        $messages = [
            'email.required' => 'Email required',
            'email.email'    => 'Email invalid',
            'password.required' => 'Password required'
        ];

        $validator = Validator::make($request->input(), $rules, $messages);
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
        if( $user->disabled_until && date('U', strtotime($user->disabled_until)) > date('U')){
            return response([
                'error' => 'Account disabled - try again later'
            ], 400);
        }
        
        if( ! password_verify( $request->password, $user->password_hash) ){
            $user->login_attempts++;

            //  If we have another failed attempt, lock for a longer period
            if( $user->login_attempts > 3 ){
                $lockedHours          = $user->login_attempts * 2;
                $user->disabled_until = date('Y-m-d H:i:s', strtotime('now +' . $lockedHours . ' hours'));
                $user->save();

                return response([
                    'error' => 'Too many failed attempts - account disabled for ' . $lockedHours . ' hours',
                ], 400);
            }else{
                $user->save();

                return response([
                    'error' => 'Invalid credentials'
                ], 400);
            }
        }

        $user->login_attempts    = 0;
        $user->disabled_until    = null;
        $user->last_login_at     = now();
        $user->auth_token        = str_random(255);
        $user->save();

        $account  = $user->account();
        $account->payment_methods = $account->payment_methods;
        $account->past_due_amount = number_format($account->past_due_amount, 2);

        return response([
            'message'       => 'created',
            'auth_token'    => $user->auth_token,
            'user'          => $user,
            'account'       => $account,
            'first_login'   => false
        ], 200);
    }

    /**
     * Trigger a password reset
     * 
     * @param Illuminate\Http\Request $request
     * 
     * @return Illuminate\Http\Response  
     */
    public function resetPassword(Request $request)
    {
        $rules = [
            'email' => 'required|email|max:128',
        ];

        $messages = [
            'email.required' => 'Email required',
            'email.email'    => 'Email address invalid'
        ];

        $validator = Validator::make($request->input(), $rules, $messages);
        if( $validator->fails() ){
            return response([
                'error' => $validator->errors()->first(),
            ], 400);
        }

        //  Look for user requesting password
        $user = User::where('email', $request->email)->first();
        if( ! $user ){
            return response([
                'error' => 'User not found',
            ], 400);
        }

        // Create new password reset while removing existing
        PasswordReset::where('user_id', $user->id)->delete();
        $passwordReset = PasswordReset::create([
            'user_id'    => $user->id,
            'key'        => str_random(40),
            'expires_at' => date('Y-m-d H:i:s', strtotime('now +24 hours')) 
        ]);

        Mail::to($user->email)
            ->later(now(), new PasswordResetEmail($user, $passwordReset));

        return response([
            'message' => 'success',
            'ok'      => true
        ]);
    }

    /**
     * Reset the password
     * 
     * @param Illuminate\Http\Request $request
     * @param int $userId
     * @param string $key 
     */
    public function handleResetPassword(Request $request, int $userId, string $key)
    {
        $passwordReset = PasswordReset::where('user_id', $userId)
                                        ->where('key', $key)
                                        ->where('expires_at', '>', date('Y-m-d H:i:s'))
                                        ->first();
                                        
        if( ! $passwordReset )
            return response([
                'error' => 'Invalid request'
            ], 400);

        //  Make sure it's a valid password
        $rules = [
            'password' => [
                'bail',
                'required',
                'min:8',
                'regex:/(?=.*[0-9])(?=.*[A-Z])/'
            ],
        ];

        $messages = [
            'password.required' => 'Password required',
            'password.min'      => 'Password must be at least 8 characters',
            'password.regex'    => 'Password must contain at least one digit and capital letter',
        ];

        $validator = Validator::make($request->input(), $rules, $messages);
        if( $validator->fails() )
            return back()->withErrors($validator->errors());
        
        //   Find the user and set the new password
        $user = User::find($passwordReset->user_id);
        $user->password_hash     = bcrypt($request->password);
        $user->login_attempts    = 0;
        $user->password_reset_at = now();
        $user->disabled_until    = null;
        $user->auth_token        = str_random(128);
        $user->save();

        //  Delete password reset
        $passwordReset->delete();

        $account  = $user->account();
        $account->payment_methods = $account->payment_methods;
        $account->past_due_amount = number_format($account->past_due_amount, 2);

        return response([
            'message'       => 'created',
            'auth_token'    => $user->auth_token,
            'user'          => $user,
            'account'       => $account,
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
    public function checkResetPassword(Request $request, $userId, $key)
    {
        $passwordReset = PasswordReset::where('user_id', $userId)
                                        ->where('key', $key)
                                        ->where('expires_at', '>', date('Y-m-d H:i:s'))
                                        ->first();
                                        
        if( ! $passwordReset )
            return response([
                'error' => 'Invalid request'
            ], 400);
        
        return response([
            'message' => 'success'
        ]);
    }
}
