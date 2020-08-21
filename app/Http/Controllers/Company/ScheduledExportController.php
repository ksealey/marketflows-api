<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Company;
use App\Models\Company\Report;
use App\Models\Company\ScheduledExport;
use App\Rules\EmailListRule;
use Validator;

class ScheduledExportController extends Controller
{
    static $fields = [
        'scheduled_exports.created_at',
        'scheduled_exports.updated_at',
        'scheduled_exports.last_ran_at',
        'scheduled_exports.delivery_method'
    ];

    public function list(Request $request, Company $company)
    {
        $query = ScheduledExport::where('scheduled_exports.company_id', $company->id);

        return parent::results(
            $request,
            $query,
            [],
            self::$fields,
            'scheduled_exports.created_at'
        );
    }

    public function create(Request $request, Company $company)
    {
        $validator = Validator::make($request->input(), [
            'report_id'         => 'bail|required|numeric',
            'day_of_week'       => 'bail|required|in:1,2,3,4,5,6,7',
            'hour_of_day'       => 'bail|required|numeric|min:0|max:23',
            'delivery_method'   => 'bail|required|in:email',
        ]);

        $validator->sometimes('delivery_email_addresses', ['bail', 'required', 'string', new EmailListRule()], function($input){
            return $input->delivery_method === 'email';
        });

        if( $validator->fails() )
            return response([
                'error' => $validator->errors()->first()
            ], 400);

        //  Validate report belongs to company
        $report = Report::find($request->report_id);
        if( ! $report || $report->company_id != $company->id ){
            return response([
                'error' => 'Report does not exist'
            ], 400);
        }

        $scheduledExport = ScheduledExport::create([
            'company_id'                => $company->id,
            'report_id'                 => $report->id,
            'day_of_week'               => $request->day_of_week,
            'hour_of_day'               => $request->hour_of_day,
            'next_run_at'               => ScheduledExport::nextRunAt($request->day_of_week, $request->hour_of_day, $request->user()->timezone)->setTimeZone('UTC'),
            'delivery_method'           => $request->delivery_method,
            'delivery_email_addresses'  => $request->delivery_email_addresses,
        ]);

        return response($scheduledExport, 201);
    }

    public function read(Request $request, Company $company, ScheduledExport $scheduledExport)
    {
        return response($scheduledExport);
    }

    public function update(Request $request, Company $company, ScheduledExport $scheduledExport)
    {
        $validator = Validator::make($request->input(), [
            'report_id'         => 'bail|numeric',
            'day_of_week'       => 'bail|in:1,2,3,4,5,6,7',
            'hour_of_day'       => 'bail|numeric|min:0|max:23',
            'delivery_method'   => 'bail|in:email',
        ]);

        $validator->sometimes('delivery_email_addresses', ['bail', 'required', 'string', new EmailListRule()], function($input){
            return $input->delivery_method === 'email';
        });

        if( $validator->fails() )
            return response([
                'error' => $validator->errors()->first()
            ], 400);

        if( $request->filled('report_id') ){
            $report = Report::find($request->report_id);
            if( ! $report || $report->company_id != $company->id ){
                return response([
                    'error' => 'Report does not exist'
                ], 400);
            }
            $scheduledExport->report_id = $report->id;
        }

        if( $request->filled('day_of_week') ){
            $scheduledExport->day_of_week = $request->day_of_week;
        }

        if( $request->filled('hour_of_day') ){
            $scheduledExport->hour_of_day = $request->hour_of_day;
        }

        if( $request->filled('day_of_week') || $request->filled('hour_of_day') ){
            $scheduledExport->next_run_at = ScheduledExport::nextRunAt(
                $request->day_of_week ?: $scheduledExport->day_of_week, 
                $request->hour_of_day ?: $scheduledExport->hour_of_day, 
                $request->user()->timezone
            )->setTimeZone('UTC');
        }

        if( $request->filled('delivery_method') ){
            $scheduledExport->delivery_method = $request->delivery_method;
            if( $scheduledExport->delivery_method == 'email' ){
                $scheduledExport->delivery_email_addresses = $request->delivery_email_addresses;
            }
        }

        $scheduledExport->save();

        return response($scheduledExport);
    }

    public function delete(Request $request, Company $company, ScheduledExport $scheduledExport)
    {
        $scheduledExport->delete();

        return response([
            'message' => 'Deleted'
        ]);
    }
}
