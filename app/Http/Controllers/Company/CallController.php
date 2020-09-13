<?php

namespace App\Http\Controllers\Company;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Company\PhoneNumber;
use App\Models\Company\Call;
use DB;
use Storage;

class CallController extends Controller
{
    protected $fields = [
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
        $query = Call::select([
                        'calls.*', 
                        'phone_numbers.name AS phone_number_name',
                        'companies.id AS company_id',
                        'companies.name AS company_name',
                        DB::raw(
                            'CASE
                                WHEN call_recordings.path IS NOT NULL AND call_recordings.deleted_at IS NULL
                                    THEN CONCAT(\'' . config('app.cdn_url') . '/' . '\', TRIM(BOTH \'\/\' FROM call_recordings.path))
                                ELSE NULL
                            END
                            AS recording_url'
                        ),
                        DB::raw(
                            "CASE
                                WHEN call_recordings.path IS NOT NULL AND call_recordings.deleted_at IS NULL
                                    THEN 'audio/mp3'
                                ELSE NULL
                            END
                            AS recording_mimetype"
                        ),
                        DB::raw('CONCAT(phone_numbers.country_code,phone_numbers.number) AS phone_number'),
                        DB::raw('TRIM(CONCAT(contacts.first_name, \' \', contacts.last_name)) AS caller_name'),
                        DB::raw("contacts.phone AS caller_number")
                    ])
                   ->where('calls.company_id', $company->id);

        if( $request->phone_number_id ){
            $query->where('phone_number_id', $request->phone_number_id);
        }

        $query->leftJoin('call_recordings', function($join){
            $join->on('call_recordings.call_id', 'calls.id');
        });

        //  Join non-deleted numbers
        $query->leftJoin('phone_numbers', function($join){
            $join->on('calls.phone_number_id', 'phone_numbers.id')
                 ->whereNull('phone_numbers.deleted_at');
        });

        //  Join non-deleted companies
        $query->leftJoin('companies', function($join){
            $join->on('calls.company_id', 'companies.id')
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
            ['phone_number_id' => 'numeric'],
            $this->fields,
            'calls.created_at'
        );
    }

    /**
     * Read a call
     * 
     * @param Request $request 
     * @param Company $company
     * @param Call $call
     * 
     * @return Response
     */
    public function read(Request $request, Company $company, Call $call)
    {
        $call->recording = $call->recording;

        return response($call);
    }

    /**
     * Read a call recording
     * 
     * @param Request $request 
     * @param Company $company
     * @param Call $call
     * 
     * @return Response
     */
    public function readRecording(Request $request, Company $company, Call $call)
    {
        return response($call->recording);
    }

    /**
     * Delete a call recording
     * 
     * @param Request $request 
     * @param Company $company
     * @param Call $call
     * 
     * @return Response
     */
    public function deleteRecording(Request $request, Company $company, Call $call)
    {
        $recording = $call->recording;
        if( ! $recording ){
            return response([
                'error' => 'Not found'
            ], 404);
        }

        $recording->delete();

        try{
            Storage::delete($recording->path);
        }catch(\Exception $e){
            return response([
                'error' => 'Unable to delete recording'
            ], 400);
        }

        return response([
            'message' => 'Deleted'
        ]);
    }

    /**
     * Export calls
     * 
     */
    public function export(Request $request, Company $company)
    {
        $request->merge([
            'company_id'   => $company->id,
            'company_name' => $company->name
        ]);
        
        return parent::exportResults(
            Call::class,
            $request,
            [],
            $this->fields,
            'calls.created_at'
        );
    }   
}
