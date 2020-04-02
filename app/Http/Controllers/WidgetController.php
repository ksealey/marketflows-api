<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Company\Call;
use DB;
use DateTime;
use DateTimeZone;

class WidgetController extends Controller
{
    /**
     * Fetch top call sources
     * 
     */
    public function topCallSources(Request $request)
    {
        $validator = validator($request->input(), [
            'start_date' => 'date',
            'end_date'   => 'date' 
        ]);

        if( $validator->fails() ){
            return response([ 
                'error' => $validator->errors()->first() 
            ], 400);
        }

        $user = $request->user();
        

        $query = DB::table('calls')
                    ->select([ 'calls.source as source', DB::raw('COUNT(*) AS call_count') ])
                    ->whereIn('company_id', function($query) use($user){
                        $query->select('company_id')
                              ->from('user_companies')
                              ->where('user_id', $user->id);
                    })
                    ->groupBy('calls.source')
                    ->orderBy('call_count', 'DESC')
                    ->limit(5);

        $results  = $query->get()->toArray();
        $labels   = array_column($results, 'source');
        $datasets = [
            [ 
                'data' => array_column($results, 'call_count'),
                'backgroundColor' =>  [
                    '#9086D6',
                    '#D3DEE5',
                    '#7DA9E4',
                    '#80ADBD',
                    '#F08080'
                ]
            ]
        ];

        return response([
            'title' => 'Top Call Sources',
            'type'  => 'doughnut',
            'data'  => [
                'labels'   => $labels,
                'datasets' => $datasets
            ]
        ]); 
    }

    /**
     * Fetch total calls across all companies
     * 
     */
    public function totalCalls(Request $request)
    {
        $validator = validator($request->input(), [
            'start_date' => 'date_format:Y-m-d',
            'end_date'   => 'date_format:Y-m-d' 
        ]);

        if( $validator->fails() ){
            return response([ 
                'error' => $validator->errors()->first() 
            ], 400);
        }

        $user   = $request->user();
        $userTZ = new DateTimeZone($user->timezone);
        $utcTZ  = new DateTimeZone('UTC');
        $query = DB::table('calls')
                    ->whereIn('company_id', function($query) use($user){
                        $query->select('company_id')
                              ->from('user_companies')
                              ->where('user_id', $user->id);
                    });
        
        if( $request->start_date ){
            $startDate = new DateTime($request->start_date, $userTZ);
            $startDate->setTimeZone($utcTZ);
            $query->where('created_at', '>=', $startDate);
        }

        if( $request->end_date ){
            $endDate = new DateTime($request->end_date . ' 23:59:59',$userTZ);
            $endDate->setTimeZone($utcTZ);
            $query->where('created_at', '<=', $endDate);
        }

        return response([
            'title' => 'Top Call Sources',
            'type'  => 'count',
            'data'  => [
                'count' => $query->count()
            ]
        ]); 
    }
}
