<?php

namespace App\Http\Controllers\Company;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Company\Report;
use App\Models\Company\ReportAutomation;
use App\Rules\Company\ReportConditionsRule;
use App\Rules\Company\ReportMetricRule;
use App\Rules\Company\ReportAutomationsRule;
use App\Rules\ReportDateOffsetsRule;
use App\Rules\ReportCustomDateRangesRule;
use DB;
use Validator;
use DateTime;
use DateTimeZone;

class ReportController extends Controller
{

    public function list(Request $request, Company $company)
    {
        $rules = [
            'order_by' => 'in:reports.name,reports.created_at,reports.updated_at'
        ];

        $query = DB::table('reports')
                    ->select(['reports.*'])
                    ->where('reports.company_id', $company->id);

        $searchFields = [
            'reports.name'
        ];

        return parent::results(
            $request,
            $query,
            $rules,
            $searchFields,
            'reports.created_at'
        );
    }

    /**
     * Create a report
     * 
     */
    public function create(Request $request, Company $company)
    {
        $rules = [
            'name'                      => ['bail', 'required', 'max:64'],
            'module'                    => ['bail', 'required', 'in:calls'],
            'metric'                    => ['bail', 'nullable', new ReportMetricRule($request->module)],
            'conditions'                => ['bail', 'nullable', 'json', new ReportConditionsRule($request->module)],
            'order'                     => ['bail', 'in:asc,desc'],
            'date_unit'                 => ['bail', 'required', 'in:YEARS,90_DAYS,60_DAYS,28_DAYS,14_DAYS,7_DAYS,DAYS,CUSTOM,ALL_TIME'],
            'export_separate_tabs'      => ['bail', 'boolean'],
            'automations'               => ['bail', 'nullable', 'json', new ReportAutomationsRule()],
        ];

        $validator = validator($request->input(), $rules);
        $validator->sometimes('date_offsets', ['bail', 'required', 'json', new ReportDateOffsetsRule()], function($input){
            return in_array($input->date_unit, ['YEARS','90_DAYS','60_DAYS','28_DAYS','14_DAYS','7_DAYS', 'DAYS']);
        });
        $validator->sometimes('date_ranges', ['bail', 'required', 'json', new ReportCustomDateRangesRule()], function($input){
            return $input->date_unit === 'CUSTOM';
        });

        if( $validator->fails() ){
            return response([
                'error' => $validator->errors()->first()
            ], 400);
        }

        $user = $request->user();
        $report = Report::create([
            'company_id'                => $company->id,
            'user_id'                   => $user->id,
            'name'                      => $request->name,
            'module'                    => $request->module,
            'metric'                    => $request->metric ?: null,
            'conditions'                => $request->conditions ? $request->conditions : null,
            'order'                     => $request->order ?: 'asc',
            'date_unit'                 => $request->date_unit,
            'date_offsets'              => $request->date_unit === 'CUSTOM' || $request->date_unit === 'ALL_TIME' ? null : $request->date_offsets,
            'date_ranges'               => $request->date_unit === 'CUSTOM' ? $request->date_ranges : null,
            'export_separate_tabs'      => $request->has('export_separate_tabs') ? $request->export_separate_tabs : true,
        ]);

        if( $request->automations ){
            $targetTZ     = new DateTimeZone('UTC');
            $automations  = json_decode($request->automations);
            $inserts      = [];
            foreach( $automations as $automation ){
                $inserts[] = [
                    'report_id'         => $report->id,
                    'type'              => $automation->type,
                    'email_addresses'   => json_encode($automation->email_addresses),
                    'day_of_week'       => $automation->day_of_week,
                    'time'              => $automation->time,
                    'run_at'            => ReportAutomation::runAt($automation->day_of_week, $automation->time, new DateTimeZone($user->timezone)),
                    'created_at'        => now(),
                    'updated_at'        => now()
                ];
            }
            ReportAutomation::insert($inserts);
        }

        $report->automations = $report->automations;

        return response($report, 201);
    }

    /**
     * Read a report
     * 
     */
    public function read(Request $request, Company $company, Report $report)
    {
        $report->automations = $report->automations;

        return response($report);
    }

    /**
     * Update a report
     * 
     */
    public function update(Request $request, Company $company, Report $report)
    {
        $rules = [
            'name'                      => ['bail', 'max:64'],
            'module'                    => ['bail', 'in:calls'],
            'metric'                    => ['bail', 'nullable', new ReportMetricRule($request->module ?: $report->module)],
            'conditions'                => ['bail', 'nullable', 'json', new ReportConditionsRule($request->module ?: $report->module)],
            'order'                     => ['bail', 'in:asc,desc'],
            'date_unit'                 => ['bail', 'in:YEARS,90_DAYS,60_DAYS,28_DAYS,14_DAYS,7_DAYS,DAYS,CUSTOM,ALL_TIME'],
            'export_separate_tabs'      => ['bail', 'boolean'],
            'automations'               => ['bail', 'nullable', 'json', new ReportAutomationsRule()],
        ];

        $validator = validator($request->input(), $rules);
        $validator->sometimes('date_offsets', ['bail', 'required', 'json', new ReportDateOffsetsRule()], function($input){
            return in_array($input->date_unit, ['YEARS','90_DAYS','60_DAYS','28_DAYS','14_DAYS','7_DAYS' ,'DAYS']);
        });
        $validator->sometimes('date_ranges', ['bail', 'required', 'json', new ReportCustomDateRangesRule()], function($input){
            return $input->date_unit === 'CUSTOM';
        });
        if( $validator->fails() )
            return response([
                'error' => $validator->errors()->first()
            ], 400);

        if( $request->has('name') )
            $report->name = $request->name;

        if( $request->has('module') )
            $report->module = $request->module;

        if( $request->has('metric') )
            $report->metric = $request->metric ?: null;

        if( $request->has('order') )
            $report->order = $request->order;

        if( $request->has('date_unit') ){
            $report->date_unit = $request->date_unit;
            if( $report->date_unit === 'CUSTOM' ){
                $report->date_offsets = null;
                if( $request->has('date_ranges') ){
                    $report->date_ranges = $request->date_ranges;
                }
            }elseif( $report->date_unit === 'ALL_TIME' ){
                $report->date_offsets = null;
                $report->date_ranges  = null;
            }else{
                $report->date_ranges = null;
                if( $request->has('date_offsets') ){
                    $report->date_offsets = $request->date_offsets;
                }
            }
        }

        if( $request->has('export_separate_tabs') )
            $report->export_separate_tabs = $request->export_separate_tabs;

        if( $request->has('automations') ){
            ReportAutomation::where('report_id', $report->id)->delete();

            if( $request->automations ){
                $targetTZ     = new DateTimeZone('UTC');
                $automations  = json_decode($request->automations);
                $inserts      = [];
                foreach( $automations as $automation ){
                    $inserts[] = [
                        'report_id'         => $report->id,
                        'type'              => $automation->type,
                        'email_addresses'   => json_encode($automation->email_addresses),
                        'day_of_week'       => $automation->day_of_week,
                        'time'              => $automation->time,
                        'run_at'            => ReportAutomation::runAt($automation->day_of_week, $automation->time, new DateTimeZone($request->user()->timezone)),
                        'created_at'        => now(),
                        'updated_at'        => now()
                    ];
                }
                ReportAutomation::insert($inserts);
            }
        }
        
        $report->save();

        $report->automations = $report->automations;
        
        return response($report, 200);
    }

    /**
     * Delete a report
     * 
     * @param Request $request
     * @param Company $company
     * @param Report $report
     * 
     * @return Response
     */
    public function delete(Request $request, Company $company, Report $report)
    {
        //  Remove automations
        ReportAutomation::where('report_id', $report->id)->delete();

        //  Remove report
        $report->delete();

        return response([ 'message' => 'deleted' ]);
    }

    /**
     * View a report's chart
     * 
     */
    public function chart(Request $request, Company $company, Report $report)
    {
        $user = $request->user();

        return response(
            $report->chart(new DateTimeZone($user->timezone))
        );
    }

    /**
     * Export a report's results
     * 
     */
    public function export(Request $request, Company $company, Report $report)
    {
        $user     = $request->user();
        $timezone = new DateTimeZone($user->timezone);

        if( $report->recordCount($timezone) > 2500 ){
            return response([
                'error' => 'No more than 2500 records can be exported at a time - Please modify your date range.'
            ], 400);
        }

        $report->export($timezone);
    }
}
