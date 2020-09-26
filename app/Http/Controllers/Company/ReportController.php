<?php

namespace App\Http\Controllers\Company;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Company\Report;
use App\Models\Company\ScheduledExport;
use App\Rules\ConditionsRule;
use App\Services\ReportService;
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
        'reports.module',
        'reports.type',
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

    public $reportService;

    public function __construct(ReportService $reportService)
    {
        $this->reportService = $reportService;
    }

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

        $validator->sometimes('group_by', 'required|in:' . implode(',', $this->conditionFields), function($input){
            return $input->type === 'count';
        });

        if( $validator->fails() ){
            return response([
                'error' =>  $validator->errors()->first()
            ], 400);
        }

        $user     = $request->user();
        $dateType = $request->date_type ?: 'ALL_TIME';
        $report   = Report::create([
            'account_id'    => $company->account_id,
            'company_id'    => $company->id,
            'created_by'    => $user->id,
            'name'          => $request->name,
            'module'        => $request->module,
            'type'          => $request->type,
            'date_type'     => $dateType,
            'group_by'      => $request->type == 'count' ? $request->group_by : null,
            'last_n_days'   => $dateType == 'LAST_N_DAYS' ? $request->last_n_days : null,
            'start_date'    => $dateType == 'CUSTOM' ? $request->start_date : null,
            'end_date'      => $dateType == 'CUSTOM' ? $request->end_date : null,
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
            $report->data = $report->run();
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
        $validator->sometimes('group_by', 'required|in:' . implode(',', $this->conditionFields), function($input){
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

        if( $request->with_data ){
            $report->data = $report->run();
        }

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

        $user   = $request->user();
        $report = new Report();
        $report->fill([
            'account_id'            => $company->account_id,
            'company_id'            => $company->id,
            'created_by'            => $user->id,
            'name'                  => 'Calls',
            'module'                => 'calls',
            'type'                  => 'timeframe',
            'date_type'             => $request->date_type,
            'group_by'              => null,
            'last_n_days'           => $request->date_type == 'LAST_N_DAYS' ? $request->last_n_days : null,
            'start_date'            => $request->date_type == 'CUSTOM' ? $request->start_date : null,
            'end_date'              => $request->date_type == 'CUSTOM' ? $request->end_date : null,
            'conditions'            => $request->conditions,
            'vs_previous_period'    => $request->vs_previous_period ?: 0,
            'link'                  => route('report-total-calls', [
                'company' => $company->id
            ])
        ]);
       
        $report->data = $report->run();

        return response($report);
    }

    /**
     * Get the call source data
     * 
     * 
     */
    public function callSources(Request $request, Company $company)
    {
        $validator = $this->getDateFilterValidator($request->input(), [
            'group_by' => 'required|in:' . implode(',', $this->conditionFields)
        ]);

        if( $validator->fails() ){
            return response([
                'error' => $validator->errors()->first()
            ], 400);
        }

        $user   = $request->user();
        $report = new Report();
        $report->fill([
            'account_id'            => $company->account_id,
            'company_id'            => $company->id,
            'created_by'            => $user->id,
            'name'                  => 'Call Sourcing',
            'module'                => 'calls',
            'type'                  => 'count',
            'date_type'             => $request->date_type,
            'group_by'              => $request->group_by,
            'last_n_days'           => $request->date_type == 'LAST_N_DAYS' ? $request->last_n_days : null,
            'start_date'            => $request->date_type == 'CUSTOM' ? $request->start_date : null,
            'end_date'              => $request->date_type == 'CUSTOM' ? $request->end_date : null,
            'conditions'            => $request->conditions,
            'vs_previous_period'    => 0,
            'link'                  => route('report-call-sources', [
                'company' => $company->id
            ])
        ]);
       
        $report->data = $report->run();

        return response($report);
    }
    

    protected function reportDates(Request $request, Company $company)
    {
        $timezone = $request->user()->timezone;
        $dateType = $request->date_type ?: 'ALL_TIME';

        if( $dateType === 'ALL_TIME' ){
            return $this->getAllTimeDates($company->created_at, $timezone);
        }elseif($dateType === 'LAST_N_DAYS' ){
            return $this->getLastNDaysDates($request->last_n_days, $timezone);
        }else{
            return $this->getDateFilterDates($dateType, $timezone, $request->start_date, $request->end_date);
        }
    }
}
