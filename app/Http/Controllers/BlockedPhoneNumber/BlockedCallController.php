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
    static $fields = [
        'blocked_calls.created_at',
        'blocked_calls.updated_at',
        'phone_numbers.number',
        'phone_numbers.name'
    ];

    public function list(Request $request, Company $company, BlockedPhoneNumber $blockedPhoneNumber)
    {
        $query = BlockedCall::select([
                        'blocked_calls.*', 
                        'phone_numbers.number', 
                        'phone_numbers.country_code', 
                        'phone_numbers.name'
                    ])
                    ->where('blocked_calls.blocked_phone_number_id', $blockedPhoneNumber->id)
                    ->leftJoin('phone_numbers', 'phone_numbers.id', 'blocked_calls.phone_number_id');

        return parent::results(
            $request,
            $query,
            [],
            static::$fields,
            'blocked_calls.created_at'
        );
    }

    /**
     * Export results
     * 
     */
    public function export(Request $request, Company $company, BlockedPhoneNumber $blockedPhoneNumber)
    {
        $request->merge([
            'company_id'                => $company->id,
            'company_name'              => $company->name,
            'blocked_phone_number_id'   => $blockedPhoneNumber->id,
            'blocked_phone_number_name' => $blockedPhoneNumber->name
        ]);

        return parent::exportResults(
            BlockedCall::class,
            $request,
            [],
            self::$fields,
            'blocked_phone_numbers.created_at'
        );
    }
}
