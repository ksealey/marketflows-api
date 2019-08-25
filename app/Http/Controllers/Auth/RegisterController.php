<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Helpers\Formatter;
use App\Models\Account;
use App\Models\Company;
use App\Models\User;
use App\Models\UserCompany;
use App\Models\Role;
use App\Models\Auth\EmailVerification;
use App\Mail\Auth\EmailVerification as UserEmailVerificationMail;
use Validator;
use DB;
use Exception;
use Mail;
use Log;

class RegisterController extends Controller
{
    /**
     * Handle an incoming company registration
     * 
     * @param Illuminate\Http\Request
     * 
     * @return Illuminate\Http\Response
     */
    public function register(Request $request)
    {
        $rules = [
            'company_name'          => 'bail|required|min:4|max:255',
            'first_name'            => 'bail|required|min:2|max:64',
            'last_name'             => 'bail|required|min:2|max:64',
            'email'                 => 'bail|required|email|max:255|unique:users,email',
            'country_code'          => 'bail|digits_between:1,6',
            'area_code'             => 'bail|digits_between:1,6',
            'phone'                 => 'bail|required|digits_between:10,16',
            'password' => [
                'bail',
                'required',
                'min:8',
                'regex:/(?=.*[0-9])(?=.*[A-Z])/'
            ],
            'timezone'              => 'bail|required|timezone',
        ];

        $messages = [
            'company_name.required'         => 'Company name required',
            'company_name.min'              => 'Company name must be at least 4 characters',
            'company_name.max'              => 'Company name cannot exceed 255 characters',
            'first_name.required'           => 'First name required',
            'first_name.min'                => 'First name must be at least 2 characters',
            'first_name.max'                => 'First name cannot exceed 64 characters',
            'last_name.required'            => 'Last name required',
            'last_name.min'                 => 'Last name must be at least 2 characters',
            'last_name.max'                 => 'Last name cannot exceed 64 characters',
            'email.required'                => 'Email required',
            'email.email'                   => 'Email invalid',
            'email.max'                     => 'Email cannot exceed 255 characters',
            'email.unique'                  => 'Email already registered',
            'country_code.digits_between'   => 'Country code must be between 1 and 6 digits',
            'area_code.digits_between'      => 'Area code must be between 1 and 6 digits',
            'phone.required'                => 'Phone required',
            'phone.digits_between'          => 'Phone must be numeric and be between 10 and 16 digits',     
            'password.required'             => 'Password required',
            'password.min'                  => 'Password must be at least 8 characters',
            'password.regex'                => 'Password must contain at least one digit and capital letter',
            'timezone.required'             => 'Time Zone required',
            'timezone.timezone'             => 'Time Zone invalid'
        ];

        $validator = Validator::make($request->input(), $rules, $messages);
        if( $validator->fails() ){
            return response([
                'error' => $validator->errors()->first(),
            ], 400);
        }

        DB::beginTransaction();

        try{
            //  Create account
            $account = Account::create([
                'name' => $request->company_name
            ]);

            //  Create company
            $company = Company::create([
                'account_id' => $account->id,
                'name'       => $request->company_name
            ]);

            //  Ship with a sample role for reporting
            Role::createReportingRole($account);

            //  Create user
            $user = User::create([
                'account_id'    => $account->id,
                'company_id'    => $company->id,
                'is_admin'      => true,
                'first_name'    => $request->first_name,
                'last_name'     => $request->last_name,
                'email'         => $request->email,
                'country_code'  => $request->country_code,
                'area_code'     => $request->area_code,
                'phone'         => $request->phone,
                'timezone'      => $request->timezone,
                'password_hash' => bcrypt($request->password),
                'auth_token'    => str_random(128),
            ]);

            //  Tie user to company
            UserCompany::create([
                'user_id'    => $user->id,
                'company_id' => $company->id
            ]);

            //  Add verification mail to queue
            Mail::to($user->email)
                ->later( now(), new UserEmailVerificationMail($user) );
        }catch(Exception $e){
            DB::rollBack();
            
            throw $e;
        }

        DB::commit(); 
        
        return response([
            'message'       => 'created',
            'auth_token'    => $user->auth_token,
            'user'          => $user,
        ], 201);
    }
}
