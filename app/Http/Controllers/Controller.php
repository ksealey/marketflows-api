<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use App\Traits\HasUserEvents;
use App\Traits\Helpers\HandlesDateFilters;
use App\Models\User;
use App\Rules\DateFilterRule;
use Validator;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests, HasUserEvents, HandlesDateFilters;

    public function listRecords(Request $request, $query, $additionalRules = [], $timezone = 'UTC', callable $formatter = null)
    {
        $rules = array_merge([
            'limit'     => 'numeric',
            'page'      => 'numeric',
            'order_by'  => 'in:created_at,updated_at',
            'order_dir' => 'in:asc,desc',
            'search'    => 'max:255',
            'from_date' => [new DateFilterRule()]
        ], $additionalRules);

        $validator = Validator::make($request->input(), $rules);

        $validator->sometimes('to_date', ['required', new DateFilterRule()], function($input){
            return $input->has('from_date');
        });

        if( $validator->fails() ){
            return response([
                'error' => $validator->errors()->first()
            ], 400);
        }

        $limit      = intval($request->limit) ?: 250;
        $limit      = $limit > 250 ? 250 : $limit;
        $page       = intval($request->page)  ?: 1;
        $orderBy    = $request->order_by  ?: 'created_at';
        $orderDir   = strtoupper($request->order_dir) ?: 'DESC';
        $search     = $request->search;

        if( $request->from_date ){
            $endDate   = $this->endDate(json_decode($request->to_date), $timezone); 

            $startDate = $this->startDate(json_decode($request->from_date), $endDate, $timezone); 

            $query->where('created_at', '>=', $startDate)
                  ->where('created_at', '<=', $endDate);
        }

        $resultCount = $query->count();
        $records     = $query->offset(( $page - 1 ) * $limit)
                             ->limit($limit)
                             ->orderBy($orderBy, $orderDir)
                             ->get();
        if( $formatter )
            $records = $formatter($records);

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
