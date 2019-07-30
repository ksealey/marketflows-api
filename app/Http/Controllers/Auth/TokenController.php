<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Auth\BlacklistedToken;
use \Firebase\JWT\JWT;
use Validator;
use Exception;

class TokenController extends Controller
{
    public function token(Request $request)
    {
        $rules = [
            'grant_type'    => 'required|in:refresh_token',
            'refresh_token' => 'required'
        ];

        $messages = [
            'grant_type.required' => 'Grant type required',
            'grant_type.in' => 'Grant type invalid',
            'refresh_token.required' => 'Refresh token required',
        ];

        $validator = Validator::make($request->input(), $rules, $messages);
        if( $validator->fails() ){
            return response([
                'error' => $validator->errors()->first(),
                'ok'    => false
            ], 400);
        }

        try{
            $jwt = JWT::decode($request->refresh_token, env('APP_KEY'), array('HS256'));
        }catch(Exception $e){
            return response([
                'error' => 'Invalid token',
                'ok'    => false
            ], 401);
        }

        //  Check blacklist for token
        if( BlacklistedToken::where('token', $request->refresh_token)->first() ){
            return response([
                'error' => 'Unable to generate token',
                'ok'    => false
            ], 401);
        }

        $user = User::find($jwt->sub);
        if( ! $user ){
            return response([
                'error' => 'User not found',
                'ok'    => false
            ], 400);
        }

        return response([
            'message'       => 'success',
            'ok'            => true,
            'bearer_token'  => $user->getBearerToken()
        ], 200);
    }
}
