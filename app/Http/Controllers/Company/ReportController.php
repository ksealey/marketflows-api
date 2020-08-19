<?php

namespace App\Http\Controllers\Company;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Company\Report;
use App\Models\Company\ScheduledExport;
use App\Rules\ConditionsRule;
use DB;
use Validator;
use DateTime;
use DateTimeZone;
use \Carbon\Carbon;
use App\Traits\Helpers\HandlesDateFilters;

class ReportController extends Controller
{
    use HandlesDateFilters;

    static $fields = [
        'reports.name',
        'reports.created_at',
        'reports.updated_at'
    ];

    protected $conditionFields = [
        'calls.type',
        'calls.category',
        'calls.sub_category',
        'calls.source',
        'calls.medium',
        'calls.content',
        'calls.campaign',
        'calls.recording_enabled',
        'calls.forwarded_to',
        'calls.duration',
        'calls.first_call',
        'contacts.first_name',
        'contacts.last_name',
        'contacts.number',
        'contacts.city',
        'contacts.state',
    ];

    protected $groupByFields = [
        'calls.type',
        'calls.category',
        'calls.sub_category',
        'calls.source',
        'calls.medium',
        'calls.content',
        'calls.campaign',
        'calls.recording_enabled',
        'calls.forwarded_to',
        'calls.duration',
        'calls.first_call',
        'contacts.first_name',
        'contacts.last_name',
        'contacts.number',
        'contacts.city',
        'contacts.state',
    ];

    /**
     * List reports
     * 
     * @param Request $request
     * @param Company $company 
     */
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
     * @param Request $request
     * @param Company $company
     */
    public function create(Request $request, Company $company)
    {
        $validator = $this->getDateFilterValidator($request->input(), [
            'name'          => 'bail|required|max:64',
            'module'        => 'bail|required|in:calls',
            'type'          => 'bail|required|in:timeframe,count',
            'conditions'    => ['bail', 'nullable', 'json', new ConditionsRule($this->conditionFields) ]
        ]);

        $validator->sometimes('group_by', 'required|in:' . implode(',', $this->groupByFields), function($input){
            return $input->type === 'count';
        });

        if( $validator->fails() ){
            return response([
                'error' =>  $validator->errors()->first()
            ], 400);
        }

        $user = $request->user();
        $report = Report::create([
            'account_id'    => $company->account_id,
            'company_id'    => $company->id,
            'created_by'    => $user->id,
            'name'          => $request->name,
            'module'        => $request->module,
            'type'          => $request->type,
            'date_type'     => $request->date_type,
            'group_by'      => $request->type == 'count' ? $request->group_by : null,
            'last_n_days'   => $request->date_type == 'LAST_N_DAYS' ? $request->last_n_days : null,
            'start_date'    => $request->date_type == 'CUSTOM' ? $request->start_date : null,
            'end_date'      => $request->date_type == 'CUSTOM' ? $request->end_date : null,
            'conditions'    => $request->conditions
        ]);

        return response($report, 201);
    }

    /**
     * Read a report
     * 
     * @param Request $request
     * @param Company $company
     * @param Report $report
     */
    public function read(Request $request, Company $company, Report $report)
    {
        if( $request->with_data ){
                $report->run();

                $startDate = clone $report->startDate;
                $endDate   = clone $report->endDate;
                $datasets = [];

                if( $report->type === 'timeframe' ){
                    $labels     = $this->timeframeLabels($startDate, $endDate);
                    $datasets[] = [
                        'label' => $startDate->format('M j, Y') . ($startDate->diff($endDate)->days ? (' - ' . $endDate->format('M j, Y')) : ''),
                        'data'  => $this->lineDatasetData($report->results, $startDate, $endDate),
                        'total' => $this->total($report->results)
                    ];
                }else{
                    $pieces   = explode('.', $report->group_by);
                    $groupBy  = end($pieces);
                    $labels   = $this->countLabels($report->results, $groupBy);
                    $groupKeys = array_column($report->results->toArray(), $groupBy);
                    $datasets = [
                        [
                            'label' => $startDate->format('M j, Y') . ($startDate->diff($endDate)->days ? (' - ' . $endDate->format('M j, Y')) : ''),
                            'data'  => $this->barDatasetData($report->results, $groupBy, $groupKeys),
                            'total' => $this->total($report->results)
                        ]
                    ];
                }

                $data = [
                    'type'     => $report->type,
                    'title'    => $report->name,
                    'labels'   => $labels,
                    'datasets' => $datasets,
                ];

                $report->data = $data;
        }

        return response($report);
    }

    /**
     * Update a report
     * 
     * @param Request $request
     * @param Company $company
     * @param Report $report
     */
    public function update(Request $request, Company $company, Report $report)
    {
        $validator = $this->getDateFilterValidator($request->input(), [
            'name'          => 'bail|max:64',
            'module'        => 'bail|in:calls',
            'type'          => 'bail|in:timeframe,count',
            'conditions'    => ['bail', 'nullable', 'json', new ConditionsRule($this->conditionFields) ]
        ]);

        $validator->sometimes('start_date', 'required|date', function($input){
            return $input->date_type === 'CUSTOM';
        });
        $validator->sometimes('end_date', 'required|date|after_or_equal:start_date', function($input){
            return $input->date_type === 'CUSTOM';
        });
        $validator->sometimes('last_n_days', 'required|numeric|min:1|max:730', function($input){
            return $input->date_type === 'LAST_N_DAYS';
        });
        $validator->sometimes('group_by', 'required|in:' . implode(',', $this->groupByFields), function($input){
            return $input->type === 'count';
        });

        if( $validator->fails() ){
            return response([
                'error' =>  $validator->errors()->first()
            ], 400);
        }

        if( $request->filled('name') ){
            $report->name = $request->name;
        }
        
        if( $request->filled('module') ){
            $report->module = $request->module;
        }

        if( $request->filled('type') ){
            $report->type = $request->type;
        }

        if( $request->filled('date_type') ){
            $report->date_type = $request->date_type;
        }

        if( $request->filled('group_by') ){
            $report->group_by = $request->group_by;
        }

        if( $request->filled('last_n_days') ){
            $report->last_n_days = $request->last_n_days;
        }

        if( $request->filled('start_date') ){
            $report->start_date = $request->start_date;
        }

        if( $request->filled('end_date') ){
            $report->end_date = $request->end_date;
        }

        if( $request->filled('conditions') ){
            $report->conditions = $request->conditions;
        }

        if( $report->type !== 'count' ){
            $report->group_by = null;
        }

        if( $report->date_type !== 'CUSTOM' ){
            $report->start_date = null;
            $report->end_date = null;
        }

        if( $report->date_type !== 'LAST_N_DAYS' ){
            $report->last_n_days = null;
        }

        $report->save();

        return response($report);
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
        //  Remove report
        $report->deleted_by = $request->user()->id;
        $report->deleted_at = now();
        $report->save();

        //  Remove scheduled exports tied to report
        ScheduledExport::where('report_id', $report->id)
                       ->delete();

        return response([ 
            'message' => 'Deleted' 
        ]);
    }

    /**
     * Export list or reports
     * 
     * @param Request $request
     * @param Company $company
     * 
     * @return Response
     */
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
     * Get the total amount of calls within the given timeframe
     * 
     * @param Request $request
     * @param Company $company
     */
    public function totalCalls(Request $request, Company $company)
    {
        $validator = $this->getDateFilterValidator($request->input());
        if( $validator->fails() ){
            return response([
                'error' => $validator->errors()->first()
            ], 400);
        }
        
        list($startDate, $endDate) = $this->reportDates($request, $company);
        $dateRangeSets = [ [$startDate, $endDate] ];
        if( $request->vs_previous_period ){
            $dateRangeSets[] = $this->getPreviousDateFilterPeriod($startDate, $endDate);
        }

        $dbDateFormat = $this->getDBDateFormat($startDate, $endDate);
        $userTimezone = $request->user()->timezone;
        foreach( $dateRangeSets as $dateRangeSet ){
            list($_startDate, $_endDate) = $dateRangeSet;
            $query = DB::table('calls')
                    ->select(
                        DB::raw("DATE_FORMAT(CONVERT_TZ(created_at, 'UTC', '" . $userTimezone . "'), '" . $dbDateFormat . "') as group_by"),
                        DB::raw('COUNT(*) as count')
                    )
                     ->where('company_id', $company->id)
                     ->where(DB::raw("CONVERT_TZ(created_at, 'UTC', '" . $userTimezone . "')"), '>=', $_startDate)
                     ->where(DB::raw("CONVERT_TZ(created_at, 'UTC', '" . $userTimezone . "')"), '<=', $_endDate)
                     ->whereNull('deleted_at')
                     ->groupBy('group_by');

            $callData = $query->get();       
            $datasets[] = [
                'label' => $_startDate->format('M j, Y') . ($_startDate->diff($_endDate)->days ? (' - ' . $_endDate->format('M j, Y')) : ''),
                'data'  => $this->lineDatasetData($callData, $_startDate, $_endDate),
                'total' => $this->total($callData)
            ];
        }

        return response([
            'kind'  => 'Report',
            'url'   => route('report-total-calls', [ 
                'company' => $company->id
            ]),
            'data'  => [
                'type'     => 'timeframe',
                'title'    => 'Calls',
                'labels'   => $this->timeframeLabels($startDate, $endDate),
                'datasets' => $datasets,
            ]
        ]);
    }

    /**
     * Get the call source data
     * 
     * 
     */
    public function callSources(Request $request, Company $company)
    {
        $validator = $this->getDateFilterValidator($request->input(), [
            'group_by' => 'required|in:source,medium,campaign,content'
        ]);

        if( $validator->fails() )
            return response([
                'error' => $validator->errors()->first()
            ], 400);
        
        list($startDate, $endDate) = $this->reportDates($request, $company);
        
        $userTimezone = $request->user()->timezone;
        $groupBy      = $request->group_by;
        $callData     = DB::table('calls')
                            ->select(
                                DB::raw($groupBy),
                                DB::raw('COUNT(*) as count')
                            )
                            ->where('company_id', $company->id)
                            ->where(DB::raw("CONVERT_TZ(created_at, 'UTC', '" . $userTimezone . "')"), '>=', $startDate)
                            ->where(DB::raw("CONVERT_TZ(created_at, 'UTC', '" . $userTimezone . "')"), '<=', $endDate)
                            ->whereNull('deleted_at')
                            ->groupBy($groupBy)
                            ->orderBy('count', 'DESC')
                            ->get();

        //  Create datasets
        $labels    = $this->countLabels($callData, $groupBy);
        $groupKeys = array_column($callData->toArray(), $groupBy);
        $datasets = [
            [
                'label' => $startDate->format('M j, Y') . ($startDate->diff($endDate)->days ? (' - ' . $endDate->format('M j, Y')) : ''),
                'data'  => $this->barDatasetData($callData, $groupBy, $groupKeys),
                'total' => $this->total($callData)
            ]
        ];

        return response([
            'kind'  => 'Report',
            'url'   => route('report-call-sources', [ 
                'company' => $company->id
            ]),
            'data'  => [
                'title'    => 'Call Sourcing',
                'type'     => 'count',
                'labels'   => $labels,
                'datasets' => $datasets,
            ]
        ]);
    }


    protected function total(iterable $inputData)
    {
        return array_sum(array_column($inputData->toArray(), 'count'));
    }

    protected function timeframeLabels($startDate, $endDate)
    {
        $labels             = [];
        $timeIncrement      = $this->getTimeIncrement($startDate, $endDate);
        $comparisonFormat   = $this->getDateFormat($startDate, $endDate);
        $displayFormat      = $this->getDisplayFormat($startDate, $endDate);

        $diff               = $startDate->diff($endDate);
       
        $dateIncrementor = clone $startDate;
        $end             = clone $endDate;
        while( $dateIncrementor->format($comparisonFormat) <= $end->format($comparisonFormat) ){
            $labels[] = $dateIncrementor->format($displayFormat);

            $dateIncrementor->modify('+1 ' . $timeIncrement);
        }

        return $labels;
    }

    protected function countLabels($inputData, $groupBy)
    {
        $values = array_column($inputData->toArray(), $groupBy);
        $labels = [];
        if( count($values) <= 10 ){
            $labels = $values;
        }else{
            $labels = $values;
            $labels = array_splice($labels, 0, 9);
            $labels[] = 'Other';
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

    protected function lineDatasetData(iterable $callData, $startDate, $endDate)
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
            $data[$call->group_by] = $call->count;
        }
        
        while( $dateIncrementor->format($comparisonFormat) <= $endDate->format($comparisonFormat) ){
            $dataset[] = [
                'value' => $data[$dateIncrementor->format($comparisonFormat)] ?? 0
            ];
            $dateIncrementor->modify('+1 ' . $timeIncrement);
        }

        return $dataset;
    }

    protected function barDatasetData(iterable $inputData, string $groupedBy, iterable $groupKeys)
    {
        $dataset = [];
        $lookupMap = [];
        foreach($inputData as $data){
            $lookupMap[$data->$groupedBy] = $data->count;
        }

        $otherCount = 0;
        $hasOthers  = false;
        foreach($groupKeys as $idx => $groupKey){
            $value = $lookupMap[$groupKey] ?? 0;
            
            if( $idx >= 9 ){
                
                $otherCount += $value;
                $hasOthers = true;
            }else{
                $dataset[] = [
                    'value' => $lookupMap[$groupKey] ?? 0
                ];
            }
        }

        if( $hasOthers ){
            $dataset[] = [
                'value' => $otherCount
            ];
        }
 
        return $dataset;
    }

    protected function reportDates(Request $request, Company $company)
    {
        $timezone = $request->user()->timezone;
        $dateType = $request->date_type;

        if( $dateType === 'ALL_TIME' ){
            return $this->getAllTimeDates($company->created_at, $timezone);
        }elseif($dateType === 'LAST_N_DAYS' ){
            return $this->getLastNDaysDates($request->last_n_days, $timezone);
        }else{
            return $this->getDateFilterDates($dateType, $timezone, $request->start_date, $request->end_date);
        }
    }
}
