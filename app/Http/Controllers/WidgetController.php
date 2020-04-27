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

    /**
     * Current usage balance by item. Used for Chart.
     * 
     */
    public function billingCurrentUsageBalanceByItem(Request $request)
    {
        $account = $request->user()->account;
        $usage   = $account->currentUsage();
        $storage = $account->currentStorage();

        $datasets = [
            [ 
                'data'            => [
                    $usage['local']['numbers']['cost'], 
                    $usage['local']['minutes']['cost'], 
                    $usage['toll_free']['numbers']['cost'], 
                    $usage['toll_free']['minutes']['cost'], 
                    $storage['total']['cost'],
                ],
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
            'title' => 'Month-to-Date Balance by Item',
            'type'  => 'doughnut',
            'data'  => [
                'labels'   => ['Local Numbers', 'Local Minutes', 'Toll-Free Numbers', 'Toll-Free Minutes', 'Storage'],
                'datasets' => $datasets,
                'total'    => number_format(
                    $usage['local']['numbers']['cost'] + 
                    $usage['local']['minutes']['cost'] + 
                    $usage['toll_free']['numbers']['cost'] + 
                    $usage['toll_free']['minutes']['cost'] + 
                    $storage['total']['cost']
                , 2)
            ]
        ]); 
    }

    /**
     * Current usage balance breakdown of each item. 
     * 
     */
    public function billingCurrentUsageBalanceBreakdown(Request $request)
    {
        $user    = $request->user();
        $account = $user->account;
        $usage   = $account->currentUsage();
        $storage = $account->currentStorage();
        
        
        $userTZ             = new DateTimeZone($user->timezone);
        $billingPeriod      = $account->currentBillingPeriod();
        $startBillingPeriod = clone $billingPeriod['start'];
        $endBillingPeriod   = clone $billingPeriod['end'];

        $startBillingPeriod->setTimeZone($userTZ);
        $endBillingPeriod->setTimeZone($userTZ);

        return response([
            'title' => 'Month-to-Date Usage Breakdown',
            'type'  => 'breakdown',
            'data'  =>  [
                'total'  => number_format($usage['total']['cost'] + $storage['total']['cost'], 2),
                'items'  => [
                    [
                        'title'       => 'Billing Period: ' . $startBillingPeriod->format('M j, Y') . ' - ' . $endBillingPeriod->format('M j, Y'),
                        'description' => '',
                        'items' => []
                    ],
                    [
                        'title' => 'Local Numbers',
                        'description' => 'Owned local numbers and minutes used, including numbers deleted within the current billing period.',
                        'items' => [
                            [
                                'label'       => 'Numbers',
                                'details'     => $usage['local']['numbers']['count'],
                                'value'       => number_format($usage['local']['numbers']['cost'], 2)
                            ],
                            [
                                'label'       => 'Minutes',
                                'details'     => $usage['local']['minutes']['count'],
                                'value'       => number_format($usage['local']['minutes']['cost'], 2)
                            ],
                        ]
                    ],
                    [
                        'title' => 'Toll-Free Numbers',
                        'description' => 'Owned local numbers and minutes used, including numbers deleted within the current billing period.',
                        'items' => [
                            [
                                'label'       => 'Numbers',
                                'details'     => number_format($usage['toll_free']['numbers']['count']),
                                'value'       => number_format($usage['toll_free']['numbers']['cost'],2)
                            ],
                            [
                                'label'       => 'Minutes',
                                'details'     => number_format($usage['toll_free']['minutes']['count']),
                                'value'       => number_format($usage['toll_free']['minutes']['cost'],2)
                            ],
                        ]
                    ],
                    [
                        'title'       => 'Storage',
                        'description' => 'Storage costs for files and call recordings.',
                        'items' => [
                            [
                                'label'       => 'Call Recordings',
                                'details'     => number_format($storage['call_recordings']['size_gb'],2) . ' GB',
                                'value'       => number_format($storage['call_recordings']['cost'],2)
                            ],
                            [
                                'label'       => 'Files',
                                'details'     => number_format($storage['files']['size_gb'],2) . ' GB',
                                'value'       => number_format($storage['files']['cost'], 2)
                            ]
                        ]
                    ],
                    
                ]
            ]
        ]); 
    }

    /**
     * Get the next bill information
     * 
     */
    public function billingNextBill(Request $request)
    {
        $account = $request->user()->account;
        $storage = $account->currentStorage();
        $usage   = $account->currentUsage();

        return response([
            'title' => 'Month-to-Date Balance Breakdown',
            'type'  => 'breakdown',
            'data'  => [
                'total' => number_format($storage['total']['cost'] + $usage['total']['cost'] + $account->monthly_fee, 2),
                'items' => [
                    [
                        'title' => 'Monthly Service Fee',
                        'description' => 'The monthly service fee charged for your account type.',
                        'items' => [
                            [
                                'label'       => $account->pretty_account_type,
                                'details'     => '',
                                'value'       => number_format($account->monthly_fee, 2)
                            ]
                        ]
                    ],
                    [
                        'title' => 'Usage',
                        'description' => 'The balance owed for all usage. This includes additional numbers and minutes along with call recordings and caller id lookups.',
                        'items' => [
                            [
                                'label'       => 'Total Usage',
                                'details'     => '',
                                'value'       => number_format($usage['total']['cost'], 2)
                            ]
                        ]
                    ],
                    [
                        'title' => 'Storage',
                        'description' => 'Storage costs for current period.',
                        'items' => [
                            [
                                'label'       => 'Total Storage',
                                'details'     => number_format($storage['total']['size_gb'],2) . 'GB',
                                'value'       => number_format($storage['total']['cost'],2)
                            ]
                        ]
                    ],
                ]
            ]
        ]); 
    }
}
