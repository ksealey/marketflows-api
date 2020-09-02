<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Models\User;
use App\Models\Company;
use App\Mail\AddUser as AddUserEmail;
use App\Mail\Auth\EmailVerification as UserEmailVerificationMail;
use App\Rules\CompanyListRule;
use Validator;
use DB;
use Mail;
use Exception;

class UserController extends Controller
{
    public $fields = [
        'users.id',
        'users.first_name',
        'users.last_name',
        'users.email',
        'users.role',
        'users.created_at',
        'users.updated_at'
    ];

    public function list(Request $request)
    {
        $user  = $request->user();
        $query = User::where('users.account_id', $user->account_id);
        if( $request->exclude_self )
            $query->where('id', '!=', $user->id);
        
        return parent::results(
            $request,
            $query,
            [],
            $this->fields,
            'users.created_at'
        );
    }

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
        if( $validator->fails() ){
            return response([
                'error' => $validator->errors()->first()
            ], 400);
        }

        DB::beginTransaction();

        try{
            //  Create this user
            $user = User::create([
                'account_id'                => $creator->account_id,
                'role'                      => $request->role,
                'timezone'                  => $request->timezone,
                'first_name'                => $request->first_name,
                'last_name'                 => $request->last_name,
                'email'                     => $request->email,
                'password_reset_token'      => str_random(128), // To allow password reset
                'password_hash'             => str_random(64), // Jibberish
                'auth_token'                => str_random(255),
                'email_verified_at'         => now() // No need for verification
            ]);

            Mail::to($user)
                ->queue(new AddUserEmail($creator, $user));
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
        $rules = [
            'role'       => 'bail|in:' . implode(',', User::roles()),
            'timezone'   => 'bail|timezone',
            'first_name' => 'bail|min:1',
            'last_name'  => 'bail|min:1',
            'login_disabled' => 'bail|boolean',
            'email'      => [
                'bail',
                'email',
                'max:128',
                Rule::unique('users')->where(function ($query) use($user){
                    $query->where('id', '!=', $user->id);
                })
            ],
        ];

        $validator = validator($request->input(), $rules);
        
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

            Mail::to($user)
                ->queue(new UserEmailVerificationMail($user));
        }

        if( $request->filled('login_disabled') )
            $user->login_disabled = !!$request->login_disabled;

        $user->save();

        return response($user);
    }

    public function delete(Request $request, User $user)
    {
        $me = $request->user();
        if( $user->id === $me->id ){
            return response([
                'error' => 'You cannot delete your own account!'
            ], 400);
        }
        
        $user->deleted_at = now();
        $user->deleted_by = $me->id;
        $user->email      = '__DELETED__' . $user->email . '__DELETED__';
        $user->save();

        return response([
            'message' => 'Deleted'
        ]);
    }

    /**
     * Export results
     * 
     */
    public function export(Request $request)
    {
        return parent::exportResults(
            User::class,
            $request,
            [],
            $this->fields,
            'users.created_at'
        );
    }
}
