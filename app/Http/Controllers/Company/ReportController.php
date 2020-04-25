<?php

namespace App\Http\Controllers\Company;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Company\Report;
use App\Models\Company\ReportAutomation;
use App\Rules\ConditionsRule;
use App\Rules\DateComparisonRule;
use App\Rules\AutomationsRule;
use App\Rules\ReportDateOffsetsRule;
use App\Rules\ReportCustomDateRangesRule;
use DB;
use Validator;
use DateTime;
use DateTimeZone;

class ReportController extends Controller
{
    static $fields = [
        'reports.name',
        'reports.created_at',
        'reports.updated_at'
    ];

    static $metricFields = [
        'calls.source',
        'calls.medium',
        'calls.campaign',
        'calls.content',
        'calls.category',
        'calls.sub_category',
        'calls.caller_city',
        'calls.caller_state',
        'calls.caller_zip',
        'phone_numbers.name'
    ];

    public function list(Request $request, Company $company)
    {
        
        $query = Report::where('reports.company_id', $company->id);

        return parent::results(
            $request,
            $query,
            [],
            self::$fields,
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
            'timezone'                  => ['bail', 'timezone'],
            'metric'                    => ['bail', 'nullable', 'in:' . implode(',',self::$metricFields)],
            'metric_order'              => ['bail', 'in:asc,desc'],
            'date_type'                 => ['bail', 'required', 'in:CUSTOM,LAST_7_DAYS,LAST_14_DAYS,LAST_28_DAYS,LAST_30_DAYS,LAST_60_DAYS,LAST_90_DAYS,YEAR_TO_DATE,ALL_TIME'],
            'comparisons'               => ['bail', 'nullable', 'json', new DateComparisonRule()],
            'conditions'                => ['bail', 'nullable', 'json', new ConditionsRule(self::$metricFields)],
            'automations'               => ['bail', 'nullable', 'json', new AutomationsRule()]
        ];
        $validator = validator($request->input(), $rules);
        $validator->sometimes('start_date', 'bail|required|date', function($input){
            return $input->date_type === 'CUSTOM';
        });
        $validator->sometimes('end_date', 'bail|required|date|after_or_equal:start_date', function($input){
            return $input->date_type === 'CUSTOM';
        });

        if( $validator->fails() )
            return response([
                'error' => $validator->errors()->first()
            ], 400);

        $user = $request->user();
        
        //  If this is a comparison, do not allow offsets of more then 90 days(Clutters charts)
        $reportData = [
            'timezone'                  => $request->timezone ?: $user->timezone,
            'company_id'                => $company->id,
            'user_id'                   => $user->id,
            'name'                      => $request->name,
            'module'                    => $request->module,
            'metric'                    => $request->metric ?: null,
            'metric_order'              => $request->metric_order ?: 'desc',
            'date_type'                 => $request->date_type,
            'start_date'                => $request->date_type === 'CUSTOM' ? $request->start_date : null,
            'end_date'                  => $request->date_type === 'CUSTOM' ? $request->end_date : null,
            'comparisons'               => $request->comparisons ? json_decode($request->comparisons) : [],
            'conditions'                => $request->conditions  ? json_decode($request->conditions)  : []
        ];

        $report = new Report();
        $report->fill($reportData);
        if( $report->dateOffset() > 90 ){
            return response([
                'error' => 'Time span between dates cannot exceed 90 days'
            ], 400);
        }

        $report = Report::create($reportData);
        if( $request->automations ){
            $user         = $request->user();
            $timezone     = new DateTimeZone($user->timezone);
            $automations  = json_decode($request->automations);
            $inserts      = [];
            $utcTZ        = new DateTimeZone('UTC');
            foreach( $automations as $automation ){
                $time = new DateTime($automation->time, $timezone);
                $time->setTimeZone($utcTZ);
                $inserts[] = [
                    'report_id'         => $report->id,
                    'type'              => $automation->type,
                    'email_addresses'   => json_encode($automation->email_addresses),
                    'day_of_week'       => $automation->day_of_week,
                    'time'              => $time->format('H:i:s'),
                    'created_at'        => now(),
                    'updated_at'        => now()
                ];
            }
            ReportAutomation::insert($inserts);
        }

        $report->automations = $report->automations ?: [];

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
            'timezone'                  => ['bail', 'timezone'],
            'metric'                    => ['bail', 'nullable', 'in:' . implode(',',self::$metricFields)],
            'metric_order'              => ['bail', 'in:asc,desc'],
            'date_type'                 => ['bail', 'in:CUSTOM,LAST_7_DAYS,LAST_14_DAYS,LAST_28_DAYS,LAST_30_DAYS,LAST_60_DAYS,LAST_90_DAYS,YEAR_TO_DATE,ALL_TIME'],
            'comparisons'               => ['bail', 'nullable', 'json', new DateComparisonRule()],
            'conditions'                => ['bail', 'nullable', 'json', new ConditionsRule(self::$metricFields)],
            'automations'               => ['bail', 'nullable', 'json', new AutomationsRule()]
        ];
        $validator = validator($request->input(), $rules);
        $validator->sometimes('start_date', 'bail|required|date', function($input){
            return $input->date_type === 'CUSTOM';
        });
        $validator->sometimes('end_date', 'bail|required|date|after_or_equal:start_date', function($input){
            return $input->date_type === 'CUSTOM';
        });

        if( $validator->fails() )
            return response([
                'error' => $validator->errors()->first()
            ], 400);

        if( $request->has('name') )
            $report->name = $request->name;

        if( $request->has('module') )
            $report->module = $request->module;

        if( $request->has('timezone') )
            $report->timezone = $request->timezone;
            
        if( $request->has('metric') )
            $report->metric = $request->metric ?: null;

        if( $request->has('metric_order') )
            $report->metric_order = $request->metric_order;

        if( $request->has('date_type') )
            $report->date_type = $request->date_type;

        if( $request->has('start_date') && $request->date_type === 'CUSTOM' )
            $report->start_date = $request->start_date;

        if( $request->has('end_date') && $request->date_type === 'CUSTOM' )
            $report->end_date = $request->end_date;

        if( $request->has('comparisons') )
            $report->comparisons = json_decode($request->comparisons) ?: [];

        if( $request->has('conditions') )
            $report->conditions = json_decode($request->conditions) ?: [];
        
        if( $report->dateOffset() > 90 ){
            return response([
                'error' => 'Time span between dates cannot exceed 90 days'
            ], 400);
        }

        if( $request->has('automations') ){
            ReportAutomation::where('report_id', $report->id)->delete();
            if( $request->automations ){
                $user         = $request->user();
                $timezone     = new DateTimeZone($user->timezone);
                $automations  = json_decode($request->automations);
                $inserts      = [];
                $utcTZ        = new DateTimeZone('UTC');
                foreach( $automations as $automation ){
                    $time = new DateTime($automation->time, $timezone);
                    $time->setTimeZone($utcTZ);
                    $inserts[] = [
                        'report_id'         => $report->id,
                        'type'              => $automation->type,
                        'email_addresses'   => json_encode($automation->email_addresses),
                        'day_of_week'       => $automation->day_of_week,
                        'time'              => $time->format('H:i:s'),
                        'created_at'        => now(),
                        'updated_at'        => now()
                    ];
                }
                ReportAutomation::insert($inserts);
            }
        }
        
        $report->save();

        $report->automations = $report->automations ?: [];
        
        return response($report, 200);
    }

    public function export(Request $request, Company $company)
    {
        $request->merge([
            'company_id'   => $company->id,
            'company_name' => $company->name
        ]);
        
        return parent::exportResults(
            Report::class,
            $request,
            [],
            self::$fields,
            'reports.created_at'
        );
    }   

    /**
     * Bulk delete reports
     * 
     */
    public function bulkDelete(Request $request, Company $company)
    {
        $user = $request->user();

        $validator = validator($request->input(), [
            'ids' => ['required','json']
        ]);

        if( $validator->fails() ){
            return response([
                'error' => $validator->errors()->first()
            ], 400);
        }

        $reportIds = array_values(json_decode($request->ids, true) ?: []);
        $reportIds = array_filter($reportIds, function($item){
            return is_string($item) || is_numeric($item);
        });

        $reports = Report::whereIn('id', $reportIds)
                            ->whereIn('company_id', function($query) use($user){
                                    $query->select('company_id')
                                        ->from('user_companies')
                                        ->where('user_id', $user->id);
                            })
                            ->get()
                            ->toArray();

        $reportIds = array_column($reports, 'id');

        if( count($reportIds) ){
            ReportAutomation::whereIn('report_id', $reportIds)->delete();
            Report::whereIn('id', $reportIds)->delete();
        }
        
        return response([
            'message' => 'Deleted.'
        ]);
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
    public function charts(Request $request, Company $company, Report $report)
    {
        return response( $report->charts() );
    }

    /**
     * Export a report's results
     * 
     */
    public function exportReport(Request $request, Company $company, Report $report)
    {
        $report->export();
    }
}
