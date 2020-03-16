<?php

namespace App\Http\Controllers\Company;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Company\Report;
use App\Rules\Company\ReportMetricRule;
use App\Rules\Company\ReportFieldsRule;
use App\Rules\ReportDateOffsetsRule;
use DB;
use Validator;

class ReportController extends Controller
{

    public function list(Request $request, Company $company)
    {
        //  Set additional rules
        $rules = [
            'order_by' => 'in:company_reports.name,company_reports.created_at,company_reports.updated_at'
        ];

        //  Build Query
        $query = DB::table('company_reports')
                    ->select(['company_reports.*'])
                    ->where('company_reports.company_id', $company->id);

        $searchFields = [
            'company_reports.name'
        ];

        //  Pass along to parent for listing
        return parent::results(
            $request,
            $query,
            $rules,
            $searchFields,
            'company_reports.created_at'
        );
    }


    public function create(Request $request, Company $company)
    {
        $rules = [
            'name'          => ['bail', 'required', 'max:64'],
            'module'        => ['bail', 'required', 'in:calls'],
            'fields'        => ['bail', 'required', new ReportFieldsRule($request->module)],
            'metric'        => ['bail', 'required', new ReportMetricRule($request->module)],
            'range_type'    => ['bail', 'required', 'in:YEARS,MONTHS,DAYS'],
            'date_offsets'  => ['bail', 'required', new ReportDateOffsetsRule()]
        ];

        $validator = validator($request->input(), $rules);
        if( $validator->fails() ){
            return response([
                'error' => $validator->errors()->first()
            ], 400);
        }

        $fields  = $this->stringArray($request->fields);
        $offsets = $this->intArray($request->date_offsets);

        $report = Report::create([
            'company_id'    => $company->id,
            'name'          => $request->name,
            'module'        => $request->module,
            'fields'        => json_encode($fields),
            'metric'        => $request->metric,
            'range_type'    => $request->range_type,
            'date_offsets'  => json_encode($offsets)
        ]);

        return response($report, 201);
    }

    public function read(Request $request, Company $company, Report $report)
    {
        return response($report);
    }

    public function update(Request $request, Company $company, Report $report)
    {
        $rules = [
            'name'          => ['bail', 'max:64'],
            'module'        => ['bail', 'in:calls'],
            'fields'        => ['bail', new ReportFieldsRule($request->module)],
            'metric'        => ['bail', new ReportMetricRule($request->module)],
            'range_type'    => ['bail', 'in:YEARS,MONTHS,DAYS'],
            'date_offsets'  => ['bail', new ReportDateOffsetsRule()]
        ];

        $validator = validator($request->input(), $rules);
        if( $validator->fails() )
            return response([
                'error' => $validator->errors()->first()
            ], 400);

        $fields  = $request->has('fields') ? $this->stringArray($request->fields) : $report->fields;
        $offsets = $request->has('date_offsets') ? $this->intArray($request->date_offsets) : $report->date_offsets;
        if( $request->has('name') )
            $report->name = $request->name;
        if( $request->has('module') )
            $report->module = $request->module;
        if( $request->has('fields') )
            $report->fields = json_encode($fields);
        if( $request->has('metric') )
            $report->metric = $request->metric;
        if( $request->has('range_type') )
            $report->range_type = $request->range_type;
        if( $request->has('date_offsets') )
            $report->date_offsets = json_encode($offsets);

        $report->save();

        $report->fields       = $fields;
        $report->date_offsets = $offsets; 

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
        $report->delete();

        return response([
            'message' => 'deleted'
        ]);
    }

    protected function intArray($fields)
    {
        $arr = $this->stringArray($fields);

        $arr = array_map(function($item){
            return intval($item);
        }, $arr);

        return $arr;
    }

    protected function stringArray($fields)
    {
        return explode(',', $fields); 
    }
}
