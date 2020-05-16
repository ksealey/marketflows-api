<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class OnlineController extends Controller
{
    /**
     * Entry point for all web sessions
     * 
     */
    public function init(Request $request)
    {
        $rules = [
            'company_id'   => 'bail|required|exists:companies,id',
            'persisted_id' => 'uuid'
        ];

        $validator = validator($request->input(), $rules);
        if( $validator->fails() ){
            
        }
    }
}
