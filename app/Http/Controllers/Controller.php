<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Traits\Helpers\HandlesDateFilters;
use App\Models\User;
use App\Rules\DateRangeRule;
use App\Rules\SearchFieldsRule;
use DateTime;

use Validator;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests, HandlesDateFilters;

    public function results(Request $request, $query, $additionalRules = [], $searchFields = [], $rangeField = 'created_at', $orderDir = 'DESC')
    {
        $rules = array_merge([
            'limit'         => 'numeric',
            'page'          => 'numeric',
            'order_by'       => 'in:' . $rangeField,
            'order_dir'      => 'in:asc,desc',
            'search'         => 'max:255',
            'search_fields'  => [new SearchFieldsRule($searchFields)],
            'date_range'     => ['json', new DateRangeRule()],
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
        $search     = $request->search;

        $user = $request->user();


        $startDate = $this->startDate($request->date_range, $user->timezone);
        $endDate   = $this->endDate($request->date_range, $user->timezone); 

        $query->where($rangeField, '>=', $startDate->format('Y-m-d H:i:s'))
              ->where($rangeField, '<', $endDate->format('Y-m-d H:i:s'));

        if( $request->search ){
            $searchFields = $request->search_fields ? explode(',', $request->search_fields) : $searchFields;
            $searchFields = array_unique($searchFields);

            $query->where(function($query) use($request, $searchFields){
                foreach( $searchFields as $i => $searchField ){
                    if( $i === 0 )
                        $query->where($searchField, 'like', '%' . $request->search . '%');
                    else
                        $query->orWhere($searchField, 'like', '%' . $request->search . '%');
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
