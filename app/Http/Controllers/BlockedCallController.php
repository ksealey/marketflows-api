<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\BlockedPhoneNumber;
use App\Models\BlockedCall;
use DB;

class BlockedCallController extends Controller
{
    public function list(Request $request)
    {
        $query = BlockedCall::select([
                    'blocked_calls.*', 
                    'blocked_phone_numbers.name AS blocked_phone_number_name',
                    'blocked_phone_numbers.country_code AS blocked_phone_number_country_code',
                    'blocked_phone_numbers.number AS blocked_phone_number_number',
                    'phone_numbers.number AS phone_number_number', 
                    'phone_numbers.country_code AS phone_number_country_code', 
                    'phone_numbers.name AS phone_number_name',
                    'phone_numbers.company_id AS company_id',
                    'phone_numbers.deleted_at AS phone_number_deleted_at',
                    'companies.name AS company_name',
                    'companies.deleted_at AS company_deleted_at',
                ])
                ->where('blocked_calls.account_id', $request->user()->account_id)
                ->leftJoin('phone_numbers', 'phone_numbers.id', 'blocked_calls.phone_number_id')
                ->leftJoin('companies', 'companies.id', 'phone_numbers.company_id')
                ->leftJoin('blocked_phone_numbers', 'blocked_phone_numbers.id', 'blocked_calls.blocked_phone_number_id');

        return parent::results(
            $request,
            $query,
            [],
            BlockedCall::accessibleFields(),
            'blocked_calls.created_at'
        );
    }

    /**
     * Export results
     * 
     */
    public function export(Request $request)
    {
        $request->merge([
           'account_id' => $request->user()->account_id
        ]);

        return parent::exportResults(
            BlockedCall::class,
            $request,
            [],
            BlockedCall::accessibleFields(),
            'blocked_calls.created_at'
        );
    }
}
