<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Role;
use App\Rules\RoleRule;
use Validator;

class RoleController extends Controller
{
    /**
     * Create a new role
     * 
     */
    public function create(Request $request)
    {
        $rules = [
            'name'   => 'bail|required|max:255',
            'policy' => ['bail','required', 'json', new RoleRule()]
        ];

        $validator = Validator::make($request->input(), $rules);
        if( $validator->fails() ){
            return response([
                'error' =>  $validator->errors()->first()
            ], 400);
        }

        $user  = $request->user();
        
        $role = Role::create([
            'user_id' => $user->id,
            'account_id' => $user->account_id,
            'name'       => $request->name,
            'policy'     => $request->policy
        ]);

        return response([
            'message' => 'created',
            'role'    => $role
        ], 201);
    }

    /**
     * View a role
     * 
     */
    public function read(Role $role)
    {
        return response([
            'role' => $role
        ]);
    }

    /**
     * Update an existing role
     * 
     */
    public function update(Request $request, Role $role)
    {
        $rules = [
            'name'   => 'bail|required|max:255',
            'policy' => ['bail','required', 'json', new RoleRule()]
        ];

        $validator = Validator::make($request->input(), $rules);
        if( $validator->fails() ){
            return response([
                'error' =>  $validator->errors()->first()
            ], 400);
        }
        
        $role->name   = $request->name;
        $role->policy = $request->policy;
        $role->save();

        return response([
            'message' => 'updated',
            'role'    => $role
        ]);
    }

    /**
     * Delete a role
     * 
     */
    public function delete(Role $role)
    {
        $role->delete();

        return response([
            'message' => 'deleted'
        ]);
    }
}
