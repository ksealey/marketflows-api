<?php

namespace App\Http\Controllers\Company\PhoneNumber;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Company\PhoneNumber;
use App\Models\Company\PhoneNumber\Call;

class CallController extends Controller
{
    /**
     * List calls for a phone number
     * 
     * @param Request $request 
     * @param Company $company
     * @param PhoneNumber $phoneNumber
     * 
     * @return Response
     */
    public function list(Request $request, Company $company, PhoneNumber $phoneNumber)
    {
        $query = Call::where('phone_number_id', $phoneNumber->id);
        
        if( $request->search ){
            $query->where(function($query) use($search){
                $query->where('name', 'like', '%' . $search . '%')
                      ->orWhere('number', 'like', '%' . preg_replace('/[^0-9]+/', '', $search) . '%');
            });
        }

        $resultCount = $query->count();
        $records     = $query->offset($page * $limit)
                             ->limit($limit)
                             ->get();

        return response([
            'message'       => 'success',
            'calls'         => $records,
            'result_count'  => $resultCount,
            'limit'         => $limit,
            'page'          => $page + 1,
            'total_pages'   => ceil($resultCount / $limit)
        ]);
    }

    /**
     * Read a call
     * 
     * @param Request $request 
     * @param Company $company
     * @param PhoneNumber $phoneNumber
     * @param Call $call
     * 
     * @return Response
     */
    public function read(Request $request, Company $company, PhoneNumber $phoneNumber, Call $call)
    {
        $phoneNumber->company = $company;
        
        $call->phone_number = $phoneNumber;

        return response([
            'message' => 'success',
            'call'    => $call
        ]);
    }
}
