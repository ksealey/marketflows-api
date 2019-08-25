<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\User;
use App\Models\UserInvite;
use App\Models\Role;
use App\Mail\UserInvite as UserInviteEmail;
use Validator;
use Mail;
use DB;

class UserInviteController extends Controller
{
    /**
     * Invite a new user to your company
     * 
     * @param Illuminate\Http\Request $request
     * 
     * @return Illuminate\Http\Response
     */
    public function create(Request $request)
    {
        $rules = [
            'email'         => 'bail|required|email|unique:users,email',
            'companies'     => 'required|array',
            'companies.*'   => 'numeric',
            'role'          => 'numeric',
            'as_admin'      => 'boolean',
            'as_client'     => 'boolean'
        ];

        $validator = Validator::make($request->input(), $rules);
        if( $validator->fails() ){
            return response([
                'error' => $validator->errors()->first(),
                'ok'    => false
            ], 400);
        }

        $user = $request->user();
        
        if( $request->role ){
            $role = Role::find($request->role);
            if( ! $role || $role->account_id != $user->account_id ){
                return response([
                    'error' => 'Invalid role'
                ], 400);
            }
        }

        if( $request->as_admin && ! $user->is_admin )
            return response([
                'error' => 'Only admin users can send out user invites to admins'
            ], 400);

        $userInvite = UserInvite::create([
            'created_by' => $user->id,
            'email'      => $request->email,
            'role_id'    => $request->role,
            'companies'  => json_encode($request->companies),
            'as_admin'   => boolval($request->as_admin),
            'as_client'  => boolval($request->as_client),
            'key'        => str_random(40),
            'expires_at' => now()->addDays(30)
        ]);

        Mail::to($userInvite->email)
            ->later(now(), new UserInviteEmail($userInvite));

        return response([
            'message' => 'success'
        ]);
    }

    /**
     * Test reading an invite
     * 
     * @param Illuminate\Http\Request $request
     * @param  UserInvite $userInvite 
     * 
     * @return Illuminate\Http\Response
     */
    public function read(Request $request, UserInvite $userInvite)
    {
        return response([
            'message'       => 'success',
            'user_invite'   => $userInvite
        ]);
    }

    /**
     * Test reading as a public user
     * 
     * @param Illuminate\Http\Request $request
     * @param  UserInvite $userInvite 
     * @param string $key
     * 
     * @return Illuminate\Http\Response
     */
    public function publicRead(Request $request, UserInvite $userInvite, $key)
    {
        if( $userInvite->key !== $key )
            return response([
                'error' => 'Invalid key'
            ], 400);

        return response([
            'message'       => 'success',
            'user_invite'   => $userInvite
        ]);
    }

    /**
     * Test accepting an invite
     * 
    * @param Illuminate\Http\Request $request
     * @param  UserInvite $userInvite 
     * @param string $key
     * 
     * @return Illuminate\Http\Response
     */
    public function publicAccept(Request $request, UserInvite $userInvite, $key)
    {
        if( $userInvite->key !== $key )
            return response([
                'error' => 'Invalid key'
            ], 400);

        $rules = [
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

        $validator = Validator::make($request->input(), $rules);
        if( $validator->fails() ){
            return response([
                'error' => $validator->errors()->first(),
                'ok'    => false
            ], 400);
        }

        $invitedBy = User::find($userInvite->created_by);
        if( ! $invitedBy ){
            return response([
                'error' => 'The user associated with this invite no longer exists - please contact the sender and have them resend an invite'
            ], 400);
        }

        $companies = Company::where('account_id', $invitedBy->account_id)
                            ->whereIn('id', json_decode($userInvite->companies))
                            ->orderBy('name', 'ASC')
                            ->get();
        if( ! count($companies) ){
            return response([
                'error' => 'The companies associated with this invite no longer exist - please contact the sender and have them resend an invite'
            ], 400);
        }

        if( $userInvite->role_id && ! Role::find($userInvite->role_id) ){
            return response([
                'error' => 'The role associated with this invite no longer exists - please contact the sender and have them resend an invite'
            ], 400);
        }  

        //  Create user
        $user = User::create([
            'account_id'    => $invitedBy->account_id,
            // Set first company as default. This is in alpabetical order.
            'company_id'    => $companies[0]->id, 
            'role_id'       => $userInvite->role_id,
            'is_admin'      => $userInvite->as_admin,
            'is_client'     => $userInvite->as_client,
            'first_name'    => $request->first_name,
            'last_name'     => $request->last_name,
            'email'         => $request->email,
            'country_code'  => $request->country_code,
            'area_code'     => $request->area_code,
            'phone'         => $request->phone,
            'timezone'      => $request->timezone,
            'password_hash' => bcrypt($request->password),
            'auth_token'    => str_random(128),
            //  Unlike normal users, this is delivered via email so it's assumed 
            //  valid once they get here and the email hasn't changed
            'email_verified_at' => $request->email === $userInvite->email ? date('Y-m-d H:i:s')  : null
        ]);

        //  Add to pivot to tie companies to users
        //  It's inserted in the following method to save on writes when there are multiple companies
        $userCompanies = [];
        $now = date('Y-m-d H:i:s');
        foreach($companies as $company){
            $userCompanies[] = [
                'user_id'    => $user->id,
                'company_id' => $company->id,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('user_companies')->insert($userCompanies);

        $userInvite->delete();

        return response([
            'message'    => 'success',
            'auth_token' => $user->auth_token,
            'user'       => $user,
        ]);
    }

    /**
     * Invite a new user to your company
     * 
     * @param Illuminate\Http\Request $request
     * @param UserInvite $userInvite
     * 
     * @return Illuminate\Http\Response
     */
    public function delete(Request $request, UserInvite $userInvite)
    {
        $userInvite->delete();

        return response([
            'message' => 'success'
        ]);
    }
}
