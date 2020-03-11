<?php

namespace App\Http\Controllers\BlockedPhoneNumber;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\BlockedPhoneNumber;
use App\Models\BlockedPhoneNumber\BlockedCall;
use DB;

class BlockedCallController extends Controller
{
    public function list(Request $request, Company $company, BlockedPhoneNumber $blockedPhoneNumber)
    {
        $rules = [ 
            'order_by'  => 'in:phone_numbers.number,blocked_calls.created_at' 
        ];

        $searchFields = [
            'phone_numbers.number'
        ];

        $query = DB::table('blocked_calls')
                    ->select([
                        'blocked_calls.*', 
                        'phone_numbers.number AS phone_number_number', 
                        'phone_numbers.country_code AS phone_number_country_code',
                        'phone_numbers.deleted_at AS phone_number_deleted_at',
                    ])
                    ->where('blocked_calls.blocked_phone_number_id', $blockedPhoneNumber->id)
                    ->leftJoin('phone_numbers', 'phone_numbers.id', 'blocked_calls.phone_number_id');

        return parent::results(
            $request,
            $query,
            $rules,
            $searchFields,
            'blocked_calls.created_at'
        );
    }
}
