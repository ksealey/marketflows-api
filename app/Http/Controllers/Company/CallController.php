<?php

namespace App\Http\Controllers\Company;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Company\PhoneNumber;
use App\Models\Company\Call;
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
        $fields = [
            'caller_name',
            'contacts.first_name',
            'contacts.last_name',
            'contacts.phone',
            'phone_numbers.name',
            'calls.category',
            'calls.sub_category',
            'calls.status',
            'calls.source',
            'calls.medium',
            'calls.content',
            'calls.campaign',
            'calls.forwarded_to',
            'calls.created_at'
        ];

        $query = DB::table('calls')
                   ->select([
                        'calls.*', 
                        'phone_numbers.name AS phone_number_name',
                        'companies.id AS company_id',
                        'companies.name AS company_name',
                        DB::raw(
                            'CASE
                                WHEN call_recordings.path IS NOT NULL
                                    THEN CONCAT(\'' . env('CDN_URL') . '/' . '\', call_recordings.path)
                                ELSE NULL
                            END
                            AS recording_url'
                        ),
                        DB::raw('CONCAT(phone_numbers.country_code,phone_numbers.number) AS phone_number'),
                        DB::raw('TRIM(CONCAT(contacts.first_name, \' \', contacts.last_name)) AS caller_name')
                    ])
                   ->where('calls.company_id', $company->id);

        $query->leftJoin('call_recordings', function($join){
            $join->on('call_recordings.call_id', 'calls.id');
        });

        //  Join non-deleted numbers
        $query->leftJoin('phone_numbers', function($join){
            $join->on('phone_numbers.id', 'calls.phone_number_id')
                 ->whereNull('phone_numbers.deleted_at');
        });

        //  Join non-deleted companies
        $query->leftJoin('companies', function($join){
            $join->on('companies.id', 'phone_numbers.company_id')
                 ->whereNull('companies.deleted_at');
        });

        //  Join non-deleted contacts
        $query->leftJoin('contacts', function($join){
            $join->on('contacts.id', 'calls.contact_id')
                 ->whereNull('contacts.deleted_at');
        });

        return parent::results(
            $request,
            $query,
            [],
            $fields,
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
    public function read(Request $request, Company $company, Call $call)
    {
        return response($call);
    }
}
