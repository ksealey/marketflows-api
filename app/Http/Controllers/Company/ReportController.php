<?php

namespace App\Http\Controllers\Company;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Company\Report;
use App\Rules\Company\ReportMetricRule;
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
            'order_by' => 'in:company_reports.name,company_reports.created_at,company_reports.updated_at'
        ];

        $query = DB::table('company_reports')
                    ->select(['company_reports.*'])
                    ->where('company_reports.company_id', $company->id);

        $searchFields = [
            'company_reports.name'
        ];

        return parent::results(
            $request,
            $query,
            $rules,
            $searchFields,
            'company_reports.created_at'
        );
    }

    /**
     * Create a report
     * 
     */
    public function create(Request $request, Company $company)
    {
        $rules = [
            'name'          => ['bail', 'required', 'max:64'],
            'module'        => ['bail', 'required', 'in:calls'],
            'metric'        => ['bail', 'nullable', new ReportMetricRule($request->module)],
            'chart_type'    => ['bail', 'in:' . implode(',', Report::metricChartTypes())],
            'order'         => ['bail', 'in:asc,desc'],
            'date_unit'     => ['bail', 'required', 'in:YEARS,90_DAYS,60_DAYS,28_DAYS,14_DAYS,7_DAYS,DAYS,CUSTOM,ALL_TIME'],
            'date_offsets'  => ['bail', 'required_if:date_unit,YEARS,90_DAYS,60_DAYS,28_DAYS,14_DAYS,7_DAYS,DAYS', new ReportDateOffsetsRule()],
            'date_ranges'   => ['bail', 'required_if:date_unit,CUSTOM', new ReportCustomDateRangesRule()],
        ];

        $validator = validator($request->input(), $rules);
        if( $validator->fails() ){
            return response([
                'error' => $validator->errors()->first()
            ], 400);
        }

        $report = Report::create([
            'company_id'    => $company->id,
            'name'          => $request->name,
            'module'        => $request->module,
            'metric'        => $request->metric ?: null,
            'chart_type'    => $request->metric ? ($request->chart_type ?: Report::CHART_TYPE_BAR) : null,
            'order'         => $request->order ?: 'asc',
            'date_unit'     => $request->date_unit,
            'date_offsets'  => json_encode($this->intArray($request->date_offsets)),
            'date_ranges'   => $request->date_ranges ? json_encode($this->dateRangeSort($this->stringArray($request->date_ranges))) : null,
        ]);

        return response($report, 201);
    }

    /**
     * Read a report
     * 
     */
    public function read(Request $request, Company $company, Report $report)
    {
        return response($report);
    }

    /**
     * Update a report
     * 
     */
    public function update(Request $request, Company $company, Report $report)
    {
        $rules = [
            'name'          => ['bail', 'max:64'],
            'module'        => ['bail', 'required_with:metric', 'in:calls'],
            'metric'        => ['bail', 'nullable', new ReportMetricRule($request->module)],
            'chart_type'    => ['bail', 'in:' . implode(',', Report::metricChartTypes())],
            'order'         => ['bail', 'in:asc,desc'],
            'date_unit'     => ['bail', 'in:YEARS,90_DAYS,60_DAYS,28_DAYS,14_DAYS,7_DAYS,DAYS,CUSTOM,ALL_TIME'],
            'date_offsets'  => ['bail', 'required_if:date_unit,YEARS,90_DAYS,60_DAYS,28_DAYS,14_DAYS,7_DAYS,DAYS', new ReportDateOffsetsRule()],
            'date_ranges'   => ['bail', 'required_if:date_unit,CUSTOM', new ReportCustomDateRangesRule()],
        ];

        $validator = validator($request->input(), $rules);
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

        if( $report->metric ){
            $report->chart_type = $request->chart_type ?: Report::CHART_TYPE_BAR;
        }else{
            $report->chart_type = null;
        }

        if( $request->has('order') )
            $report->order = $request->order;

        if( $request->has('date_unit') )
            $report->date_unit = $request->date_unit;

        if( $request->has('date_offsets') )
            $report->date_offsets = json_encode($this->intArray($request->date_offsets));

        if( $request->has('date_ranges') )
            $report->date_ranges = json_encode($this->dateRangeSort($this->stringArray($request->date_ranges)));

        $report->save();

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
        //
        //  Remove automations
        //  ...
        // 

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
            $report->chart(
                new DateTimeZone($user->timezone)
            )
        );
    }

    /**
     * View a report's results
     * 
     */
    public function listResults(Request $request, Company $company, Report $report)
    {
        $rules = [
            'page'      => 'required|numeric|min:1',
            'limit'     => 'required|numeric|min:1,max:250',
        ];

        $validator = validator($request->input(), $rules);
        if( $validator->fails() ){
            return response([
                'error' => $validator->errors()->first()
            ], 400);
        }

        $page    = $request->page;
        $limit   = $request->limit;
        $user    = $request->user();

        $resultCount = $report->resultCount($user->timezone);
        $results     = $report->results(
            $page, 
            $limit,
            $user->timezone
        );

        $nextPage = null;
        if( $resultCount > ($page * $limit) )
            $nextPage = $page + 1;
        
        return response([
            'result_count' => $resultCount,
            'results'      => $results,
            'limit'        => $limit,
            'page'         => $page,
            'total_pages'  => ceil($resultCount / $limit),
            'next_page'    => $nextPage
        ]);
    }

    /**
     * Export a report's results
     * 
     */
    public function export(Request $request, Company $company, Report $report)
    {
        return response([
            'export' => true
        ]);
    }

    protected function intArray($items)
    {
        $items = $this->stringArray($items);
        array_walk($items, 'intval');
        return $items;
    }

    protected function stringArray($items, $order = false)
    {
        $items = explode(',', $items);
        array_walk($items, 'trim');
        return $items; 
    }

    protected function dateRangeSort($dateRanges)
    {
        usort($dateRanges, function($a, $b){
            $aTime = explode(':', $a);
            $bTime = explode(':', $b);

            $aStart = new DateTime($aTime[0]);
            $bStart = new DateTime($bTime[0]);

            return $aStart->format('U') < $bStart->format('U') ? -1 : 1;
        });
        return $dateRanges;
    }
}
