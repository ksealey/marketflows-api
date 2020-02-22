<?php

namespace App\Http\Controllers\Company;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Company\PhoneNumber;
use App\Models\Company\PhoneNumber\Call;
use DB;

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
    public function list(Request $request, Company $company)
    {
        $rules = [ 
            'order_by' => 'in:calls.caller_first_name,calls.caller_number,phone_numbers.name,calls.status,calls.source,calls.content,calls.medium,calls.campaign,calls.created_at,calls.updated_at',
        ];

        $searchFields = [
            'calls.caller_first_name',
            'calls.caller_last_name',
            'calls.caller_number',
            'phone_numbers.name',
            'calls.status',
            'calls.source',
            'calls.medium',
            'calls.content',
            'calls.campaign',
            'calls.forwarded_to'
        ];

        $query = DB::table('calls')
                   ->select([
                       'calls.*', 
                       'phone_numbers.name AS phone_number_name',
                       DB::raw('CONCAT(phone_numbers.country_code,phone_numbers.number) AS phone_number'),
                       'phone_number_pools.name AS phone_number_pool_name',
                       'companies.id AS company_id',
                       'companies.name AS company_name'
                    ])
                   ->where('calls.company_id', $company->id);

        //  Join non-deleted numbers
        $query->leftJoin('phone_numbers', function($join){
            $join->on('phone_numbers.id', 'calls.phone_number_id')
                 ->whereNull('phone_numbers.deleted_at');
        });

        //  Join non-deleted number pools
        $query->leftJoin('phone_number_pools', function($join){
            $join->on('phone_number_pools.id', 'calls.phone_number_pool_id')
                 ->whereNull('phone_number_pools.deleted_at');
        });

        //  Join non-deleted companies
        $query->leftJoin('companies', function($join){
            $join->on('companies.id', 'phone_numbers.company_id')
                 ->whereNull('companies.deleted_at');
        });

        return parent::results(
            $request,
            $query,
            $rules,
            $searchFields,
            'calls.created_at'
        );
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
