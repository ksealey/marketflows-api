<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\User;
use App\Models\Role;
use App\Models\Auth\EmailVerification;
use App\Mail\Auth\EmailVerification as UserEmailVerificationMail;
use \App\Rules\CountryRule;
use Validator;
use DB;
use Exception;
use Mail;

class RegisterController extends Controller
{
    /**
     * Handle an incoming account registration
     * 
     * @param Illuminate\Http\Request
     * 
     * @return Illuminate\Http\Response
     */
    public function register(Request $request)
    {
        $rules = [
            'account_name'          => 'bail|required|min:4|max:255',
            'plan'                  => 'bail|required|in:BASIC,AGENGY,ENTERPRISE',
            'first_name'            => 'bail|required|min:2|max:64',
            'last_name'             => 'bail|required|min:2|max:64',
            'email'                 => 'bail|required|email|max:255|unique:users,email',
            'title'                 => 'bail|max:255',
            'password' => [
                'bail',
                'required',
                'min:8',
                'regex:/(?=.*[0-9])(?=.*[A-Z])/'
            ]
        ];

        $validator = Validator::make($request->input(), $rules);
        if( $validator->fails() ){
            return response([
                'error' => $validator->errors()->first(),
            ], 400);
        }

        DB::beginTransaction();

        try{
            //  Create account
            $account = Account::create([
                'name'      => $request->account_name,
                'balance'   => 0.00,
                'plan'      => $request->plan,
                'bill_at'   => now()->addMonths(1)
            ]);
            
            //  Create an admin role
            $adminRole = Role::createAdminRole($account);
            
            //  Create a sample role for reporting
            Role::createReportingRole($account);

            //  Create this user
            $user = User::create([
                'account_id'    => $account->id,
                'role_id'       => $adminRole->id,
                'first_name'    => $request->first_name,
                'last_name'     => $request->last_name,
                'email'         => $request->email,
                'password_hash' => bcrypt($request->password),
                'auth_token'    => str_random(128),
            ]);

            //  Add verification mail to queue
            Mail::to($user->email)
                ->later(now(), new UserEmailVerificationMail($user));
        }catch(Exception $e){
            DB::rollBack();
            
            throw $e;
        }

        DB::commit(); 
        
        return response([
            'auth_token'    => $user->auth_token,
            'user'          => $user,
            'account'       => $account,
            'first_login'   => true
        ], 201);
    }
}
