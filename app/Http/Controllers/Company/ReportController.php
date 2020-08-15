<?php

namespace App\Http\Controllers\Company;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Company\Call;
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
use \Carbon\Carbon;

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
            'account_id'                => $company->account_id,
            'company_id'                => $company->id,
            'created_by'                => $user->id,
            'name'                      => $request->name,
            'module'                    => $request->module,
            'metric'                    => $request->metric ?: null,
            'metric_order'              => $request->metric_order ?: 'desc',
            'date_type'                 => $request->date_type,
            'start_date'                => $request->date_type === 'CUSTOM' ? $request->start_date : null,
            'end_date'                  => $request->date_type === 'CUSTOM' ? $request->end_date : null,
            'comparisons'               => $request->comparisons ?: null,
            'conditions'                => $request->conditions  ?: null
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
            $report->comparisons = $request->comparisons ?: null;

        if( $request->has('conditions') )
            $report->conditions = $request->conditions ?: null;
        
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
        $report->deleted_by = $request->user()->id;
        $report->deleted_at = now();
        $report->save();

        return response([ 
            'message' => 'Deleted' 
        ]);
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


    /**
     * Get the total amount of calls within the given timeframe
     * 
     * 
     */
    public function totalCalls(Request $request, Company $company)
    {
        $validator = $this->reportValidator($request);
        if( $validator->fails() ){
            return response([
                'error' => $validator->errors()->first()
            ], 400);
        }
        
        list($startDate, $endDate) = $this->reportDates($request, $company, 'calls');
        
        
        
        $dateRangeSets = [ [$startDate, $endDate] ];
        if( $request->vs_previous_period ){
            $diff            = $startDate->diff($endDate);
            $vsEndDate       = (clone $startDate)->subDays(1)->endOfDay();
            $vsStartDate     = (clone $vsEndDate)->subDays($diff->days)->startOfDay();
            $dateRangeSets[] = [$vsStartDate, $vsEndDate];
        }

        $user         = $request->user();
        $dbDateFormat = $this->getDBDateFormat($startDate, $endDate);
        foreach( $dateRangeSets as $dateRangeSet ){
            list($_startDate, $_endDate) = $dateRangeSet;
            $callData = DB::table('calls')
                    ->select(
                        DB::raw("DATE_FORMAT(CONVERT_TZ(created_at, 'UTC', '" . $user->timezone . "'), '" . $dbDateFormat . "') as group_key"),
                        DB::raw('COUNT(*) as count')
                    )
                     ->where('company_id', $company->id)
                     ->where(DB::raw("CONVERT_TZ(created_at, 'UTC', '" . $user->timezone . "')"), '>=', $_startDate->startOfDay())
                     ->where(DB::raw("CONVERT_TZ(created_at, 'UTC', '" . $user->timezone . "')"), '<=', $_endDate->endOfDay())
                     ->whereNull('deleted_at')
                     ->groupBy('group_key')
                     ->get();

            $datasets[] = [
                'label' => $_startDate->format('M j, Y') . ($_startDate->diff($_endDate)->days ? (' - ' . $_endDate->format('M j, Y')) : ''),
                'data'  => $this->datasetData($callData, $_startDate, $_endDate)
            ];
        }

        return response([
            'kind'  => 'Report',
            'url'   => route('report-total-calls', [ 
                'company' => $company->id
            ]),
            'data'  => [
                'type'     => 'line',
                'title'    => 'Calls',
                'labels'   => $this->chartLabels($startDate, $endDate),
                'datasets' => $datasets
            ]
        ]);
    }

    protected function total(iterable $callData)
    {
        return array_sum(array_column($callData->toArray(), 'count'));
    }

    protected function chartLabels($startDate, $endDate)
    {
        $labels             = [];
        $timeIncrement      = $this->getTimeIncrement($startDate, $endDate);
        $comparisonFormat   = $this->getDateFormat($startDate, $endDate);
        $displayFormat      = $this->getDisplayFormat($startDate, $endDate);

        $diff               = $startDate->diff($endDate);
       
        $dateIncrementor = clone $startDate;
        $end             = clone $endDate;
        while( $dateIncrementor->format($comparisonFormat) <= $endDate->format($comparisonFormat) ){
            $labels[] = $dateIncrementor->format($displayFormat);

            $dateIncrementor->modify('+1 ' . $timeIncrement);
        }

        return $labels;
    }

    protected function getDBDateFormat($startDate, $endDate)
    {
        $days = $startDate->diff($endDate)->days;

        if( $days == 0 ){ //  Time
            $format = '%Y-%m-%d %k:00:00';
        }elseif( $days <= 60 ){ //   Days
            $format = '%Y-%m-%d';
        }elseif( $days <= 730 ){ //  Months
            $format = '%Y-%m';
        }else{ //  Years
            $format = '%Y';
        }

        return $format;
    }

    protected function getDateFormat($startDate, $endDate)
    {
        $days = $startDate->diff($endDate)->days;

        if( $days == 0 ){ //  Time
            $format = 'Y-m-d H:00:00';
        }elseif( $days <= 60 ){ //   Days
            $format = 'Y-m-d';
        }elseif( $days <= 730 ){ //  Months
            $format = 'Y-m';
        }else{ //  Years
            $format = 'Y';
        }

        return $format;
    }

    protected function getTimeIncrement($startDate, $endDate)
    {
        $diff = $startDate->diff($endDate);

        if( $diff->days == 0 ){ //  Time
            $timeIncrement = 'hour';
        }elseif( $diff->days <= 60 ){ //   Days
            $timeIncrement = 'day';
        }elseif( $diff->days <= 730 ){ //  Months
            $timeIncrement = 'month';
        }else{ //  Years
            $timeIncrement = 'year';
        }

        return $timeIncrement;
    }

    protected function getDisplayFormat($startDate, $endDate)
    {
        $diff = $startDate->diff($endDate);

        if( $diff->days == 0 ){ //  Time
            $displayFormat = 'g:ia';
        }elseif( $diff->days <= 60 ){ //   Days
            $displayFormat = 'M j, Y';
        }elseif( $diff->days <= 730 ){ //  Months
            $displayFormat = 'M, Y';
        }else{ //  Years
            $displayFormat = 'Y';
        }

        return $displayFormat;
    }

    protected function datasetData(iterable $callData, $startDate, $endDate)
    {
        $dataset = [];
        $diff    = $startDate->diff($endDate);
       
        $timeIncrement      = $this->getTimeIncrement($startDate, $endDate);
        $comparisonFormat   = $this->getDateFormat($startDate, $endDate);
        $displayFormat      = $this->getDisplayFormat($startDate, $endDate);

        $dateIncrementor = clone $startDate;
        $end             = clone $endDate;

        $data = [];
        foreach($callData as $call){
            $data[$call->group_key] = $call->count;
        }
        
        while( $dateIncrementor->format($comparisonFormat) <= $endDate->format($comparisonFormat) ){
            $dataset[] = [
                'value' => $data[$dateIncrementor->format($comparisonFormat)] ?? 0
            ];
            $dateIncrementor->modify('+1 ' . $timeIncrement);
        }

        return $dataset;
    }

    protected function formattedData($data, $startDate, $endDate)
    {
        return [7, 22, 11];
    }

    protected function reportValidator(Request $request)
    {
        $rules = [
            'date_type'  => 'required|in:CUSTOM,YESTERDAY,TODAY,LAST_7_DAYS,LAST_30_DAYS,LAST_60_DAYS,LAST_90_DAYS,LAST_180_DAYS,ALL_TIME'
        ];

        $validator = Validator::make($request->input(), $rules);
        $validator->sometimes('start_date', 'required|date', function($input){
            return $input->date_type === 'CUSTOM';
        });
        $validator->sometimes('end_date', 'required|date|after_or_equal:start_date', function($input){
            return $input->date_type === 'CUSTOM';
        });

        return $validator;
    }

    protected function reportDates(Request $request, Company $company, string $module)
    {
        $userTimezone = $request->user()->timezone;
        $now = now();
        $now->setTimeZone($userTimezone);

        switch( $request->date_type ){
            case 'CUSTOM':
                $startDate = new Carbon($request->start_date, $userTimezone);
                $endDate   = new Carbon($request->end_date, $userTimezone);
            break;

            case 'YESTERDAY':
                $startDate = $now->subDays(1)->startOfDay();
                $endDate   = (clone $startDate)->endOfDay();
            break;

            case 'LAST_7_DAYS':
                $endDate   = $now->subDays(1)->endOfDay();
                $startDate = (clone $endDate)->startOfDay()->subDays(6);
            break;

            case 'LAST_30_DAYS':
                $endDate   = $now->subDays(1)->endOfDay();
                $startDate = (clone $endDate)->startOfDay()->subDays(29);
            break;

            case 'LAST_60_DAYS':
                $endDate   = $now->subDays(1)->endOfDay();
                $startDate = (clone $endDate)->startOfDay()->subDays(59);
            break;

            case 'LAST_90_DAYS':
                $endDate   = $now->subDays(1)->endOfDay();
                $startDate = (clone $endDate)->startOfDay()->subDays(89);
            break;

            case 'LAST_180_DAYS':
                $endDate   = $now->subDays(1)->endOfDay();
                $startDate = (clone $endDate)->startOfDay()->subDays(179);
            break;

            case 'ALL_TIME':
                $endDate     = $now->endOfDay();
                $firstRecord = DB::table($module)
                                  ->where('company_id', $company->id)
                                  ->orderBy('created_at', 'ASC')
                                  ->first();
                if( $firstRecord ){
                    $startDate = new Carbon($firstRecord->created_at);
                    $startDate->setTimeZone($userTimezone);
                    $startDate = $startDate->startOfDay();
                }else{
                    $startDate = (clone $endDate)->startOfDay();
                }
                
            break;

            default: // TODAY
                $startDate = $now->startOfDay();
                $endDate   = (clone $startDate)->endOfDay();
            break;
        }

        return [$startDate, $endDate];
    }
}
