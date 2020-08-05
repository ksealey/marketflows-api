<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use \App\Models\APICredential;

class APICredentialController extends Controller
{
    public function create(Request $request)
    {
        $rules = [
            'name' => 'required|max:64'
        ];
        $validator = validator($request->input(), $rules);
        if( $validator->fails() ){
            return response([
                'error' => $validator->errors()->first()
            ], 400);
        }

        $user        = $request->user();
        $key         = strtoupper(str_random(30));
        $secret      = str_random(30);
        $credentials = APICredential::create([
            'user_id'       => $user->id,
            'name'          => $request->name,
            'key'           => $key,
            'secret'        => bcrypt($secret)
        ]);

        return response([
            'name'      => $credentials->name,
            'key'       => $credentials->key,
            'secret'    => $secret
        ], 201);
    }

    /**
     * List api credentials
     * 
     */
    public function list(Request $request)
    {
        $fields = [
            'api_credentials.created_at',
            'api_credentials.name',
            'api_credentials.key',
        ];
        
        $user  = $request->user();
        $query = APICredential::where('user_id', $user->id);
        
        return parent::results(
            $request,
            $query,
            [],
            $fields,
            'api_credentials.created_at'
        );
    }

    public function delete(Request $request, APICredential $apiCredential)
    {
        $apiCredential->delete();

        return response([
            'message' => 'Deleted'
        ]);
    }
}
