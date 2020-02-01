<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Validator;

class AccountController extends Controller
{
    public function read(Request $request)
    {
        return response($request->user()->account);
    }

    public function update(Request $request)
    {
        $rules = [
            'name' => 'min:1|max:255'
        ];

        $validator = Validator::make($request->input());
        if( $validator->fails() ){
            return response([
                'error' => $validator->errors()->first()
            ], 400);
        }

        $account = $request->user()->account;

        //
        // Update account
        //  ...  
        // 

        return response($account);
    }
}
