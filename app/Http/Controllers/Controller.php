<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Traits\HasUserEvents;
use App\Traits\Helpers\HandlesDateFilters;
use App\Models\User;
use App\Rules\DateFilterRule;
use DateTime;

use Validator;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests, HasUserEvents, HandlesDateFilters;

    public function listRecords(Request $request, $query, $additionalRules = [], callable $formatter = null)
    {
        $rules = array_merge([
            'limit'     => 'numeric',
            'page'      => 'numeric',
            'order_by'  => 'in:created_at,updated_at',
            'order_dir' => 'in:asc,desc',
            'search'    => 'max:255',
            'from_date' => [new DateFilterRule()],
            'to_date'   => [new DateFilterRule()]
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
        $orderBy    = $request->order_by  ?: 'created_at';
        $orderDir   = strtoupper($request->order_dir) ?: 'DESC';
        $search     = $request->search;

        $user = $request->user();

        $endDate   = $request->to_date   ? $this->endDate(json_decode($request->to_date), $user->timezone) : new DateTime(); 
        $startDate = $request->from_date ? $this->startDate(json_decode($request->from_date), $endDate, $user->timezone) : new DateTime('1970-01-01 00:00:00');
       
        $query->where('created_at', '>=', $startDate)
              ->where('created_at', '<=', $endDate);

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
