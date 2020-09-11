<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Models\User;
use App\Rules\ConditionsRule;
use App\Traits\AppliesConditions;
use App\Traits\Helpers\HandlesDateFilters;
use App\Services\ExportService;
use App\Services\PhoneNumberService;
use Carbon\Carbon;
use DateTime;
use DateTimeZone;
use Validator;
use Storage;
use DB;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests, HandlesDateFilters, AppliesConditions;

    public $exportService;
    public $numberService;

    public function __construct(ExportService $exportService, PhoneNumberService $numberService)
    {
        $this->exportService = $exportService;
        $this->numberService = $numberService;
    }

    public function results(Request $request, $query, $additionalRules = [], $fields = [], $rangeField = 'created_at', callable $formatter = null)
    {
        $validator = $this->getDateFilterValidator($request->input(), array_merge([
            'limit'          => 'bail|numeric|min:1|max:250',
            'page'           => 'bail|numeric|min:1',
            'order_by'       => 'bail|in:' . $rangeField,
            'order_dir'      => 'bail|in:asc,desc',
            'conditions'     => ['bail','json', new ConditionsRule($fields)],
            'order_by'       => 'bail|in:' . implode(',', $fields)
        ], $additionalRules));

        if( $validator->fails() ){
            return response([
                'error' => $validator->errors()->first()
            ], 400);
        }

        $user = $request->user();
        list($startDate, $endDate) = $this->getDates($request);
        if( $startDate ){
            $query->where(DB::raw("CONVERT_TZ($rangeField, 'UTC', '" . $user->timezone . "')"), '>=', $startDate);
        }
        if( $endDate ){
            $query->where(DB::raw("CONVERT_TZ($rangeField, 'UTC', '" . $user->timezone . "')"), '<=', $endDate);
        }

        if( $request->conditions )
            $query = $this->applyConditions($query,  json_decode($request->conditions));
        
        $page       = intval($request->page)  ?: 1; 
        $limit      = intval($request->limit) ?: 250;
        $orderBy    = $request->order_by  ?: $rangeField;
        $orderDir   = strtoupper($request->order_dir) ?: 'desc';

        $resultCount = $query->count();
        $records     = $query->offset(( $page - 1 ) * $limit)
                             ->limit($limit)
                             ->orderBy($orderBy, $orderDir)
                             ->get();
        $nextPage = null;
        if( $resultCount > ($page * $limit) )
            $nextPage = $page + 1;
       
        if( $formatter ){
            $records = $formatter($records);
        }

        return response([
            'results'              => $records,
            'result_count'         => $resultCount,
            'limit'                => $limit,
            'page'                 => $page,
            'total_pages'          => ceil($resultCount / $limit),
            'next_page'            => $nextPage
        ]);
    }

    /**
     * Export Data
     * 
     */
    public function exportResults($model, Request $request, $additionalRules = [], $fields = [], $rangeField = 'created_at', $orderDir = 'desc', $formatter = null)
    {
        $rules = array_merge([
            'limit'         => 'bail|numeric|min:0,max:250',
            'page'          => 'bail|numeric|min:1',
            'order_by'       => 'bail|in:' . $rangeField,
            'order_dir'      => 'bail|in:asc,desc',
            'conditions'     => ['bail', 'json', new ConditionsRule($fields)],
            'start_date'     => 'bail|nullable|date_format:Y-m-d',
            'end_date'       => 'bail|nullable|date_format:Y-m-d',
            'order_by'       => 'bail|in:' . implode(',', $fields)
        ], $additionalRules);

        $validator = Validator::make($request->input(), $rules);
        if( $validator->fails() ){
            return response([
                'error' => $validator->errors()->first()
            ], 400);
        }

        $limit      = intval($request->limit) >= 0 ? $request->limit : 0;
        if( $limit ){
            $page = intval($request->page)  >= 1 ? $request->page : 1;
        }else{
            $page = 1;
        }
        
        $orderBy    = $request->order_by  ?: $rangeField;
        $orderDir   = strtoupper($request->order_dir) ?: $orderDir;
        $input      = array_merge($request->input(), [
            'order_by'    => $orderBy,
            'order_dir'   => $orderDir,
            'range_field' => $rangeField
        ]);

        $user  = $request->user();
        $query = $model::exportQuery($user, $input);

        list($startDate, $endDate) = $this->getDates($request);

        if( $startDate )
            $query->where(DB::raw("CONVERT_TZ($rangeField, 'UTC', '" . $user->timezone . "')"), '>=', $startDate);
        if( $endDate )
            $query->where(DB::raw("CONVERT_TZ($rangeField, 'UTC', '" . $user->timezone . "')"), '<=', $endDate);

        if( $request->conditions )
            $query = $this->applyConditions($query,  json_decode($request->conditions));

        $query->orderBy($orderBy, $orderDir);

        return $this->exportService
                    ->exportAsOutput($user, $input, $query, $model::exports(), $model::exportFileName($user, $input));
    }


    protected function getDates($request)
    {
        $dateType = $request->date_type ?: 'ALL_TIME';

        if( $dateType === 'ALL_TIME' ){
            $dates = null;
        }elseif( $dateType === 'LAST_N_DAYS' ){
            $dates = $this->getLastNDaysDates($request->last_n_days, $request->user()->timezone);
        }else{
            $dates = $this->getDateFilterDates($dateType, $request->user()->timezone, $request->start_date, $request->end_date);
        }
        return $dates;
    }
}
