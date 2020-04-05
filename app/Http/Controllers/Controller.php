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
use App\Jobs\ExportResultsJob;
use DateTime;
use DateTimeZone;
use Validator;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests, AppliesConditions;

    public function results(Request $request, $query, $additionalRules = [], $fields = [], $rangeField = 'created_at', $orderDir = 'desc')
    {
        $rules = array_merge([
            'limit'         => 'required|numeric|min:1|max:250',
            'page'          => 'required|numeric|min:1|max:250',
            'order_by'       => 'in:' . $rangeField,
            'order_dir'      => 'in:asc,desc',
            'conditions'     => ['json', new ConditionsRule($fields)],
            'start_date'     => 'nullable|date_format:Y-m-d',
            'end_date'       => 'nullable|date_format:Y-m-d',
            'order_by'       => 'in:' . implode(',', $fields)
        ], $additionalRules);

        $validator = Validator::make($request->input(), $rules);
        if( $validator->fails() ){
            return response([
                'error' => $validator->errors()->first()
            ], 400);
        }

        $orderBy    = $request->order_by  ?: $rangeField;
        $orderDir   = strtoupper($request->order_dir) ?: $orderDir;

        if( $request->start_date || $request->end_date){
            $user   = $request->user();
            $userTZ = new DateTimeZone($user->timezone);
            $utcTZ  = new DateTimeZone('UTC');

            if( $request->start_date ){
                $startDate = new DateTime($request->start_date . ' 00:00:00', $userTZ);
                $startDate->setTimeZone($utcTZ);
                $query->where($rangeField, '>=', $startDate->format('Y-m-d H:i:s'));
            }

            if( $request->end_date  ){
                $endDate = new DateTime($request->end_date. ' 23:59:59', $userTZ);
                $endDate->setTimeZone($utcTZ);
                $query->where($rangeField, '<=', $endDate->format('Y-m-d H:i:s'));
            }
        }

        if( $request->conditions ){
            $query = $this->applyConditions($query,  json_decode($request->conditions));
        }

        $page  = intval($request->page); 
        $limit = intval($request->limit);

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
    public function exportResults($model, Request $request, $additionalRules = [], $fields = [], $rangeField = 'created_at', $orderDir = 'desc')
    {
        $rules = array_merge([
            'limit'         => 'nullable|numeric|min:1|max:250',
            'page'          => 'nullable|numeric|min:1',
            'order_by'       => 'in:' . $rangeField,
            'order_dir'      => 'in:asc,desc',
            'conditions'     => ['json', new ConditionsRule($fields)],
            'start_date'     => 'nullable|date_format:Y-m-d',
            'end_date'       => 'nullable|date_format:Y-m-d',
            'order_by'       => 'in:' . implode(',', $fields)
        ], $additionalRules);

        $validator = Validator::make($request->input(), $rules);
        if( $validator->fails() ){
            return response([
                'error' => $validator->errors()->first()
            ], 400);
        }

        $limit      = intval($request->limit) >= 0 ? $request->limit : 0;
        $page       = intval($request->page)  >= 0 ? $request->page : 1;
        $orderBy    = $request->order_by  ?: $rangeField;
        $orderDir   = strtoupper($request->order_dir) ?: $orderDir;

        $input =  array_merge($request->input(), [
            'order_by'    => $orderBy,
            'order_dir'   => $orderDir,
            'range_field' => $rangeField
        ]);

        if( ! $limit ){
            //  Create export job and end
            //  ...
            ExportResultsJob::dispatch(
                $model,
                $request->user(),
                $input
            );

            return response([
                'message' => 'Queued'
            ]);
        }

        $query = $model::exportQuery($request->user(), $input);
        
        if( $request->start_date || $request->end_date){
            $user   = $request->user();
            $userTZ = new DateTimeZone($user->timezone);
            $utcTZ  = new DateTimeZone('UTC');

            if( $request->start_date ){
                $startDate = new DateTime($request->start_date . ' 00:00:00', $userTZ);
                $startDate->setTimeZone($utcTZ);
                $query->where($rangeField, '>=', $startDate->format('Y-m-d H:i:s'));
            }

            if( $request->end_date  ){
                $endDate = new DateTime($request->end_date. ' 23:59:59', $userTZ);
                $endDate->setTimeZone($utcTZ);
                $query->where($rangeField, '<=', $endDate->format('Y-m-d H:i:s'));
            }
        }

        if( $request->conditions )
            $query = $this->applyConditions($query,  json_decode($request->conditions));

        $query->orderBy($orderBy, $orderDir);

          
        $records = $query->offset(( $page - 1 ) * $limit)
                         ->limit($limit)
                         ->get();

        $model::onExport($records);
    }
}
