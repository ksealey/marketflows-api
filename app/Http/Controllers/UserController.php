<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Models\User;
use App\Models\Company;
use App\Models\UserCompany;
use App\Mail\AddUser as AddUserEmail;
use App\Mail\Auth\EmailVerification as UserEmailVerificationMail;
use App\Rules\CompanyListRule;
use Validator;
use DB;
use Mail;
use Exception;

class UserController extends Controller
{

    public function create(Request $request)
    {
        $creator = $request->user();

        $rules  = [
            'first_name' => 'bail|required|min:2|max:32',
            'last_name'  => 'bail|required|min:2|max:32',
            'email'      => 'bail|required|email|max:128|unique:users,email',
            'role'       => 'bail|required|in:' . implode(',', User::roles()),
            'timezone'   => 'bail|required|timezone',
        ];

        $validator = validator($request->input(), $rules);
        $validator->sometimes('companies', ['required', 'json', new CompanyListRule($creator->account_id)], function($input){
            return $input->role !== User::ROLE_ADMIN && $input->role !== User::ROLE_SYSTEM;
        });

        if( $validator->fails() ){
            return response([
                'error' => $validator->errors()->first()
            ], 400);
        }

        DB::beginTransaction();

        try{
            //  Create this user
            $resetToken = str_random(128);

            $user = User::create([
                'account_id'                => $creator->account_id,
                'role'                      => $request->role,
                'timezone'                  => $request->timezone,
                'first_name'                => $request->first_name,
                'last_name'                 => $request->last_name,
                'email'                     => $request->email,
                'password_hash'             => bcrypt($resetToken),
                'password_reset_token'      => $resetToken,
                'password_reset_expires_at' => now()->addDays(90),
                'auth_token'                => str_random(255),
                'settings'                  => [
                    'email_alerts_enabled' => true,
                    'sms_alerts_enabled'   => false,
                ]
            ]);

            //  Create company links for lower level users
            if( ! $user->canViewAllCompanies() ){
                $companyIds = json_decode($request->companies);
                $inserts    = [];
                foreach( $companyIds as $companyId ){
                    $inserts[] = [
                        'user_id'    => $user->id,
                        'company_id' => $companyId
                    ];
                }
                UserCompany::insert($inserts);
            }

            //  Send out email to user
            Mail::to($user)->send(new AddUserEmail($creator, $user));
        }catch(Exception $e){
            DB::rollBack();

            throw $e;
        }

        DB::commit();

        return response($user, 201);
    }

    public function read(Request $request, User $user)
    {
        return response($user);
    }

    public function update(Request $request, User $user)
    {
        $me    = $request->user();
        $rules = [
            'role'       => 'bail|in:' . implode(',', User::roles()),
            'timezone'   => 'bail|timezone',
            'first_name' => 'bail|min:1',
            'last_name'  => 'bail|min:1',
            'email'      => [
                'bail',
                'email',
                'max:128',
                Rule::unique('users')->where(function ($query) use($user){
                    $query->where('account_id', '!=', $user->account_id);
                })
            ],
            'phone' => 'bail|nullable|digits_between:10,13'
        ];

        $validator = validator($request->input(), $rules);
        if($validator->fails()){
            return response([
                'error' => $validator->errors()->first()
            ], 400);
        }

        if( $request->filled('role') )
            $user->role = $request->role;

        if( $request->filled('timezone') )
            $user->timezone = $request->timezone;

        if( $request->filled('first_name') )
            $user->first_name = $request->first_name;

        if( $request->filled('last_name') )
            $user->last_name = $request->last_name;

        if( $request->filled('email') && $user->email != $request->email ){
            $user->email = $request->email;
            $user->email_verified_at = null;

            Mail::to($user)->send(new UserEmailVerificationMail($user));
        }

        if( $request->has('phone') && $request->phone != $user->phone ){
            $user->phone = preg_replace('/[^0-9]+/', '', $request->phone) ?: null;
            $user->phone_verified_at = null;
        }

        $user->save();

        return response($user);
    }



    public function delete(User $user)
    {
        $user->delete();

        return response([
            'message' => 'deleted'
        ]);
    }

    public function changePassword(Request $request, User $user)
    {
        $rules = [
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

        $user->password_hash        = bcrypt($request->password);
        $user->login_attempts       = 0;
        $user->login_disabled_until = null;
        $user->auth_token           = str_random(255);
        $user->save();

        $response = [
            'message' => 'success'
        ];

        if( $user->id === $request->user()->id ){
            $response['user']       = $user;
            $response['auth_token'] = $user->auth_token;
        }

        return response($response);
    }
}
