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
use App\Jobs\ExportResultsJob;
use Carbon\Carbon;
use DateTime;
use DateTimeZone;
use Validator;
use DB;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests, HandlesDateFilters, AppliesConditions;

    public function results(Request $request, $query, $additionalRules = [], $fields = [], $rangeField = 'created_at', $orderDir = 'desc')
    {
        $validator = $this->getDateFilterValidator($request->input(), array_merge([
            'limit'         => 'bail|numeric|min:1|max:250',
            'page'          => 'bail|numeric|min:1',
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

        $user     = $request->user();
        $dateType = $request->date_type;

        if( $dateType === 'ALL_TIME' ){
            $dates = null;
        }elseif( $dateType === 'LAST_N_DAYS' ){
            $dates = $this->getLastNDaysDates($request->last_n_days, $user->timezone);
        }else{
            $dates = $this->getDateFilterDates($dateType, $user->timezone, $request->start_date, $request->end_date);
        }

        if( $dates ){
            list($startDate, $endDate) = $dates;
            $query->where($rangeField, '>=', $startDate->format('Y-m-d H:i:s'));
            $query->where($rangeField, '<=', $endDate->format('Y-m-d H:i:s'));
        }

        if( $request->conditions )
            $query = $this->applyConditions($query,  json_decode($request->conditions));
        
        $page       = intval($request->page)  ?: 1; 
        $limit      = intval($request->limit) ?: 250;
        $orderBy    = $request->order_by  ?: $rangeField;
        $orderDir   = strtoupper($request->order_dir) ?: $orderDir;

        $resultCount = $query->count();
        $records     = $query->offset(( $page - 1 ) * $limit)
                             ->limit($limit)
                             ->orderBy($orderBy, $orderDir)
                             ->get();
        $nextPage = null;
        if( $resultCount > ($page * $limit) )
            $nextPage = $page + 1;
       
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

        $input =  array_merge($request->input(), [
            'order_by'    => $orderBy,
            'order_dir'   => $orderDir,
            'range_field' => $rangeField
        ]);

        ExportResultsJob::dispatch(
            $model,
            $request->user(),
            $input
        );

        return response([
            'message' => 'queued'
        ]);
    }

    protected function getFilteringDates(Request $request)
    {
        if( ! $request->start_date)
        $timezone = $request->user()->timezone;
        $dateType = $request->date_type;
        
        switch( $request->date_type ){
            case 'CUSTOM':
                $startDate = (new Carbon($request->start_date))->startOfDay();
                $endDate   = (new Carbon($request->end_date))->endOfDay();
            break;

            case 'YESTERDAY':
                $startDate = now()->subDays(1)->startOfDay();
                $endDate   = (clone $startDate)->endOfDay();
            break;

            case 'LAST_7_DAYS':
                $endDate   = now()->subDays(1)->endOfDay();
                $startDate = (clone $endDate)->startOfDay()->subDays(6);
            break;

            case 'LAST_30_DAYS':
                $endDate   = now()->subDays(1)->endOfDay();
                $startDate = (clone $endDate)->startOfDay()->subDays(29);
            break;

            case 'LAST_60_DAYS':
                $endDate   = now()->subDays(1)->endOfDay();
                $startDate = (clone $endDate)->startOfDay()->subDays(59);
            break;

            case 'LAST_90_DAYS':
                $endDate   = now()->subDays(1)->endOfDay();
                $startDate = (clone $endDate)->startOfDay()->subDays(89);
            break;

            case 'LAST_180_DAYS':
                $endDate   = now()->subDays(1)->endOfDay();
                $startDate = (clone $endDate)->startOfDay()->subDays(179);
            break;

            case 'TODAY':
                $startDate = now()->startOfDay();
                $endDate   = (clone $startDate)->endOfDay();
                
            break;

            default: // ALL_TIME
                $startDate = null;
                $endDate   = null;
            break;
        }

        return [$startDate, $endDate];
    }
}
