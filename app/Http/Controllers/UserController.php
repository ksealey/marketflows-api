<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Company;
use App\Models\UserCompany;
use Validator;
use DB;

class UserController extends Controller
{
    /**
     * View a user
     * 
     */
    public function read(User $user)
    {
        return response($user);
    }

    /**
     * Update a user
     * 
     */
    public function update(Request $request, User $user)
    {
        $rules = [
            'first_name'            => 'bail|required|min:2|max:32',
            'last_name'             => 'bail|required|min:2|max:32',
            'email'                 => 'bail|required|email|max:128',
            'role'                  => 'bail',
            'companies'             => 'required|array',
            'companies.*'           => 'numeric',
        ];

        $validator = Validator::make($request->input(), $rules);
        if( $validator->fails() ){
            return response([
                'error' => $validator->errors()->first(),
            ], 400);
        }

        //  If the email is changes, make sure it's not in use by another user
        if( $request->email != $user->email && User::where('email', $request->email)->count() > 0){
            return response([
                'error' => 'Email address in use'
            ], 400);
        }

        //  If there is a role, make sure we own it
        if( $request->role ){
            $role = Role::find($request->role);
            if( ! $role || $role->account_id != $user->account_id ){
                return response([
                    'error' => 'Invalid role'
                ], 400);
            }
        }

        //  Check if companies changed
        $userCompanies = array_column($user->companies->toArray(), 'id'); 
        $newCompanies  = array_values($request->companies);
        sort($userCompanies);
        sort($newCompanies);
        
        if( $userCompanies != $newCompanies ){
            //  Make sure we own all companies
            $othersCompanies = Company::whereIn('id', $newCompanies)
                                      ->where('account_id', '!=', $user->account_id)
                                      ->count(); 
            if( $othersCompanies > 0 ){
                return response([
                    'error' => 'Invalid company(s) provided'
                ], 400);
            }

            $userRemovedCompanies = array_diff($userCompanies, $newCompanies);
            if( count($userRemovedCompanies) ){
                UserCompany::where('user_id', $user->id)
                            ->whereIn('company_id', $userRemovedCompanies)
                            ->delete();

                //   Set default company to first one on our list in alphabetical order
                if( in_array($user->company_id, $userRemovedCompanies) ){
                    $user->company_id = Company::whereIn('id', $newCompanies)
                                                ->orderBy('name', 'ASC')
                                                ->first()
                                                ->id;
                }
            }

            $userAddedCompanies = array_diff($newCompanies, $userCompanies);
            if( count($userAddedCompanies) ){
                $userCompanies = [];
                $now = date('Y-m-d H:i:s');
                foreach( $userAddedCompanies as $companyId ){
                    $userCompanies[] = [
                        'user_id'    => $user->id,
                        'company_id' => $companyId,
                        'created_at' => $now,
                        'updated_at' => $now
                    ];
                }
                DB::table('user_companies')->insert($userCompanies);
            }
        }

        $user->first_name   = $request->first_name;
        $user->last_name    = $request->last_name;
        $user->email        = $request->email;
        $user->save();

        return response([
            'message' => 'updated',
            'user'    => $user
        ]);
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

        $user->password_hash  = bcrypt($request->password);
        $user->login_attempts = 0;
        $user->disabled_until = null;
        $user->auth_token     = str_random(255);
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
