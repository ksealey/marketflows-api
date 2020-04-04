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
use DateTime;
use DateTimeZone;
use Validator;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    public function results(Request $request, $query, $additionalRules = [], $fields = [], $rangeField = 'created_at', $orderDir = 'DESC')
    {
        $rules = array_merge([
            'limit'         => 'numeric',
            'page'          => 'numeric',
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

        $limit      = intval($request->limit) ?: 250;
        $limit      = $limit > 250 ? 250 : $limit;
        $page       = intval($request->page)  ?: 1;
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
            $conditions = json_decode($request->conditions);
            $query->where(function($query) use($conditions){
                foreach( $conditions as $condition ){
                    if( $condition->operator === 'EQUALS' ){
                        if( ! empty($condition->inputs[0]) )
                            $query->where($condition->field, '=', $condition->inputs[0]);
                    }elseif( $condition->operator === 'NOT_EQUALS' ){
                        if( ! empty($condition->inputs[0]) )
                            $query->where($condition->field, '!=', $condition->inputs[0]);
                    }elseif( $condition->operator === 'IN' ){
                        $hasValue = false;
                        foreach($condition->inputs as $input){
                            if( $input )
                                $hasValue = true;
                        }
                        if( $hasValue )
                           $query->whereIn($condition->field, $condition->inputs);
                    }elseif( $condition->operator === 'NOT_IN' ){
                        $hasValue = false;
                        foreach($condition->inputs as $input){
                            if( $input ) 
                                $hasValue = true;
                        }
                        if( $hasValue )
                            $query->whereNotIn($condition->field, $condition->inputs);
                    }elseif( $condition->operator === 'EMPTY' ){
                        $query->where(function($query) use($condition){
                            $query->whereNull($condition->field)
                                  ->orWhere($condition->field, '=', '');
                        });
                    }elseif( $condition->operator === 'NOT_EMPTY' ){
                        $query->where(function($query) use($condition){
                            $query->whereNotNull($condition->field)
                                  ->orWhere($condition->field, '!=', '');
                        });
                    }
                }
            });
        }
       
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
}
