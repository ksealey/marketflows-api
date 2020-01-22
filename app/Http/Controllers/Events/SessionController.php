<?php

namespace App\Http\Controllers\Events;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Company\PhoneNumberPool;
use App\Models\Events\Session;
use Validator;

class SessionController extends Controller
{
    /**
     * Create a new session
     * 
     */
    public function create(Request $request)
    {
        $validator = Validator::make($request->input(), [
            'session_id' => 'bail|required|uuid',
            'company_id' => 'required|exists:companies,id'
        ]);

        if( $validator->fails() ){
            return response([
                'error' => $validator->errors()->first()
            ], 400);
        }

        //  If session already exists, throw an error
        $sessions = 

        //  Find web online pool for company
        $pool = PhoneNumberPool::where();


    }
}
