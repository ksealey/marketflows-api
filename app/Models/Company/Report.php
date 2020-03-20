<?php

namespace App\Models\Company;

use Illuminate\Database\Eloquent\Model;
use DB;
use DateTime;
use DateTimeZone;

class Report extends Model
{
    protected $table = 'company_reports';

    protected $fillable = [
        'company_id',
        'name',
        'module',
        'metric',
        'order',
        'date_unit',
        'date_offsets',
        'date_ranges',
        'chart_type',
        'is_system_report'
    ];

    protected $appends = [
        'link',
        'kind'
    ];

    protected $timezone;

    protected $resultSets = [];

    static protected $moduleLabels  = [
        'calls' => 'Calls'
    ];

    static protected $metrics = [
        'calls' => [
            'calls.source'      => 'Source',
            'calls.medium'      => 'Medium',
            'calls.campaign'    => 'Campaign',
            'calls.content'     => 'Content',
            'calls.category'    => 'Category',
            'calls.sub_category'=> 'Sub-Category',
            'calls.caller_city' => 'Caller City',
            'calls.caller_state'=> 'Caller State',
            'calls.caller_zip'  => 'Caller Zip',
            'phone_number_name' => 'Dialed Phone Number'
        ]
    ];

    static protected $exposedFields = [
        'calls' => [
            'fields' => [
                'calls.company_id',
                'companies.name as company_name',
                'calls.toll_free',
                'calls.category',
                'calls.sub_category',
                'calls.phone_number_pool_id',
                'phone_number_pools.name AS phone_number_pool_name',
                'calls.phone_number_id',
                'calls.dialed_number',
                'calls.dialed_city',
                'calls.dialed_state',
                'calls.dialed_zip',
                'calls.dialed_country',
                'phone_numbers.name AS phone_number_name',
                'calls.caller_first_name',
                'calls.caller_last_name',
                'calls.caller_country_code',
                'calls.caller_number',
                'calls.caller_city',
                'calls.caller_state',
                'calls.caller_zip',
                'calls.caller_country',
                'calls.source',
                'calls.medium',
                'calls.content',
                'calls.campaign',
                'calls.recording_enabled',
                'calls.caller_id_enabled',
                'calls.forwarded_to',
                'calls.id',
                'calls.direction',
                'calls.status',
                'calls.duration',
                'calls.created_at'
            ]
        ]
    ];

    const CHART_TYPE_LINE     = 'LINE'; // No metric
    const CHART_TYPE_BAR      = 'BAR';  
    const CHART_TYPE_PIE      = 'PIE';
    const CHART_TYPE_DOUGHNUT = 'DOUGHNUT';

    /**
     * Attributes
     * 
     */
    public function getLinkAttribute()
    {
        return route('read-report', [
            'companyId' => $this->company_id,
            'reportId'  => $this->id
        ]);
    }

    public function getKindAttribute()
    {
        return 'Report';
    }

    public function getDateOffsetsAttribute($dateOffsets)
    {
        return json_decode($dateOffsets);
    }

    public function getDateRangesAttribute($dateRanges)
    {
        if( ! $dateRanges ) return null;

        return json_decode($dateRanges);
    }


    /**
     * Fetch a chart
     * 
     */
    public function chart(DateTimeZone $timezone)
    {
        $dataLabels     = $this->dataLabels($timezone);
        $datasets       = $this->datasets($timezone);
        $moduleLabel    = $this->moduleLabel();
        $metricLabel    = $this->metricLabel();
        $rangeLabel     = $this->rangeLabel();
  
        return [
            'data' => [
                'labels'   => $dataLabels,
                'datasets' => $datasets,
            ],
            'module_label' => $moduleLabel,
            'metric_label' => $metricLabel,
            'range_label'  => $rangeLabel,
            'type'         => $this->hasMetric() ? $this->chart_type : self::CHART_TYPE_LINE,
            'step_size'    => 10,
        ];
    }

    /**
     * Convert a report's settings into date ranges, ordered from earliest to most recent
     * 
     */
    public function dateRanges(DateTimeZone $timezone)
    {
        $dateSets = [];

        if( $this->date_unit === 'CUSTOM' ){
            $dateRanges = $this->date_ranges;
            foreach( $dateRanges as $dateRange ){
                $dates      = explode(':', $dateRange);
                $startDate  = new DateTime($dates[0] . ' 00:00:00.000000', $timezone); 
                $endDate    = new DateTime($dates[1] . ' 23:59:59.999999', $timezone); 
                $dateSets[] = [
                    'start' => $startDate,
                    'end'   => $endDate
                ];
            }
        }elseif( $this->date_unit === 'ALL_TIME' ){
            $result = DB::table($this->module)
                            ->select('created_at')
                            ->where('company_id', $this->company_id)
                            ->orderBy('created_at', 'ASC')
                            ->limit(1)
                            ->get();

            if( count($result) ){
                $startDate  = new DateTime($result[0]->created_at);
            }else{
                $startDate  = new DateTime(); 
            }

            $endDate = new DateTime();

            $startDate->setTimeZone($timezone); 
            $endDate->setTimeZone($timezone); 

            $dateSets[] = [
                'start' => $startDate,
                'end'   => $endDate
            ];
        }else{
            foreach( $this->date_offsets as $offset ){
                $dateSets[] = [
                    'start' => $this->startDate($timezone, $offset),
                    'end'   => $this->endDate($timezone, $offset)
                ];
            }
        }

        usort($dateSets, function($a, $b){
            return $a['start']->format('U') > $b['start']->format('U') ? 1 : -1;
        });

        return $dateSets;
    }

    /**
     * Get a module's label
     * 
     */
    public function moduleLabel()
    {
        return self::$moduleLabels[$this->module];
    }

    /**
     * Get a metric's label
     * 
     */
    public function metricLabel()
    {
        if( ! $this->hasMetric() )
            return null;

        if( self::metricExists($this->module, $this->metric) )
            return self::$metrics[$this->module][$this->metric];
        return null;
    }

    /**
     * Get the range's label. This will display under the main labels describing the type of data in labels.
     * 
     */
    public function rangeLabel()
    {
        if( $this->hasMetric() )
            return null;

        switch( $this->date_unit ){
            case 'DAYS':
            case 'YEARS':
            case 'ALL_TIME':
                return null;
            default:
                return 'Day';
        }
    }

    public function dataLabels(DateTimeZone $timezone)
    {
        if( $this->hasMetric() )
            return $this->metricLabels($timezone);

        switch( $this->date_unit ){
            case 'DAYS':
                return $this->hourLabels();
            case '7_DAYS':
                return $this->dayLabels(7);
            case '14_DAYS':
                return $this->dayLabels(14);
            case '28_DAYS':
                return $this->dayLabels(28);
            case '60_DAYS':
                return $this->dayLabels(60);
            case '90_DAYS':
                return $this->dayLabels(90);
            case 'YEARS':
                return $this->monthLabels();
            case 'CUSTOM':
                return $this->dayLabels($this->customDateRangeDays());
            case 'ALL_TIME':
                return $this->allTimeLabels($timezone)['labels'];
            default:
                return [];
            break;
        }
    }

    /**
     * Labels
     * 
     */
    public function metricLabels(DateTimeZone $timezone)
    {
        //  Use date ranges as bottom labels
        $dateRanges = $this->dateRanges($timezone);
        $labels     = [];

        foreach( $dateRanges as $dateRange ){
            switch( $this->date_unit ){
                case 'YEARS':
                    $labels[] = $dateRange['start']->format('Y');
                break;

                case 'ALL_TIME':
                    $labels[] = $this->moduleLabel() . ' by ' . $this->metricLabel();
                break;

                default: 
                    $start = $dateRange['start']->format('M d, Y');
                    $end   = $dateRange['end']->format('M d, Y');
                    $labels[] = $start . ( $start == $end ? '' : (' - ' . $end));
                break;
            }
        }

        return $labels;
    }

    public function hourLabels()
    {
        return [
            '12-1AM', '1-2AM', '2-3AM', '3-4AM', '4-5AM', '5-6AM', '6-7AM', '7-8AM', '8-9AM', '9-10AM', '10-11AM', '11AM-12PM', 
            '12-1PM', '1-2PM', '2-3PM', '3-4PM', '4-5PM', '5-6PM', '6-7PM', '7-8PM', '8-9PM', '9-10PM', '10-11PM', '11PM-12AM'
        ];
    }

    public function dayLabels($days)
    {
        $labels    = [];
        for( $i=1; $i<=$days; $i++ ){
            $labels[] = $i;
        }
        return $labels;
    }

    public function monthLabels()
    {
        return [
            'January', 'February', 'March', 'April', 'May', 'June', 
            'July', 'August', 'September', 'October', 'November', 'December' 
        ];
    }

    public function allTimeLabels($timezone)
    {
        $dateRanges = $this->dateRanges($timezone);
        $dateFormat = null;
        $labels     = [];

        $first = $dateRanges[0]['start'];
        $last  = $dateRanges[0]['end'];
        $diff  = $first->diff($last);
       
        if( $diff->y > 1 ){
            $dateFormat = 'Y';
            while( $first->format('Y') <= $last->format('Y') ){
                $labels[] = $first->format($dateFormat);
                $first->modify('+1 year');
            }
        }else{
            $dateFormat = 'M, y';
            while( $first->format('Ym') <= $last->format('Ym') ){
                $labels[] = $first->format($dateFormat);
                $first->modify('+1 month');
            }
        }

        return [
            'labels'      => $labels,
            'date_format' => $dateFormat
        ];
    }

    /**
     * Datasets
     * 
     */
    public function datasets($timezone)
    {
        if( $this->hasMetric() )
            return $this->metricDatasets($timezone);

        switch( $this->date_unit ){
            case 'DAYS':
                return $this->dayDatasets($timezone);
            case '7_DAYS':
                return $this->dayRangeDatasets(7, $timezone);
            case '14_DAYS':
                return $this->dayRangeDatasets(14, $timezone);
            case '28_DAYS':
                return $this->dayRangeDatasets(28, $timezone);
            case '60_DAYS':
                return $this->dayRangeDatasets(60, $timezone);
            case '90_DAYS':
                return $this->dayRangeDatasets(90,$timezone);
            case 'YEARS':
                return $this->yearDatasets($timezone);
            case 'CUSTOM':
                return $this->dayRangeDatasets($this->customDateRangeDays(), $timezone);
            case 'ALL_TIME':
                return $this->allTimeDatasets($timezone);
            default:
                break;
        }
    }
    
    /**
     * Get a dataset for a report that has a metric
     * 
     * @return array
     */
    public function metricDatasets(DateTimeZone $timezone)
    {
        // Prep
        $datasets       = [];
        $metricCounts   = [];
        $metricKey      = explode('.', $this->metric);
        $metricKey      = end($metricKey);
        $dateRanges     = $this->dateRanges($timezone);

        //  First get a list of all the metrics for the timeframe
        $earliestDateRange = $dateRanges[0];
        $latestDateRange   = $dateRanges[count($dateRanges)-1];

        if( $this->module == 'calls' ){
            $query = DB::table('calls')
                        ->select([DB::raw('COUNT(*) AS total'), $this->metric])
                        ->where(DB::raw("CONVERT_TZ(calls.created_at,'UTC','" . $timezone->getName() . "')"), '>=', $earliestDateRange['start']->format('Y-m-d H:i:s.u'))
                        ->where(DB::raw("CONVERT_TZ(calls.created_at,'UTC','" . $timezone->getName() . "')"), '<=', $latestDateRange['end']->format('Y-m-d H:i:s.u'))
                        ->where('calls.company_id', $this->company_id)
                        ->groupBy($this->metric)
                        ->orderBy('total', $this->order);
       
            $metricList = array_column($query->get()->toArray(), $metricKey);
            $datasets  = [];
            foreach( $metricList as $idx => $metricValue ){
                if( $idx >= 10 )
                    break;

                $datasets[$metricValue] = [];
            }
            
            foreach( $dateRanges as $idx => $dateRange ){
                $results = DB::table('calls')
                            ->select([DB::raw('COUNT(*) AS total'), $this->metric])
                            ->where(DB::raw("CONVERT_TZ(calls.created_at,'UTC','" . $timezone->getName() . "')"), '>=', $dateRange['start']->format('Y-m-d H:i:s.u'))
                            ->where(DB::raw("CONVERT_TZ(calls.created_at,'UTC','" . $timezone->getName() . "')"), '<=', $dateRange['end']->format('Y-m-d H:i:s.u'))
                            ->where('calls.company_id', $this->company_id)
                            ->groupBy($this->metric)
                            ->get();
                
                $resultMap = [];
                foreach( $results as $result ){
                    $resultMap[$result->$metricKey] = $result->total;
                }

                $counter    = 0;
                $otherTotal = 0;
                foreach($metricList as $metricValue){
                    $total = !empty($resultMap[$metricValue]) ? $resultMap[$metricValue] : 0;
                    if( $counter >= 10 ){
                        $otherTotal += $total; // Attribute to "Other" and move on
                        continue;
                    }
                    
                    $datasets[$metricValue][] = $total;
                    $counter++;
                }

                if( $counter >= 10 )
                    $datasets['Other'][] = $otherTotal;
            }
        }

        $returnSet = [];
        foreach( $datasets as $key => $data ){
            $returnSet[] = [
                'label' => $key,
                'data'  => $data
            ];
        }

        return $returnSet;
    }

    public function dayRangeDatasets($days, $timezone)
    {
        $datasets = [];
        
        if( $this->module == 'calls' ){
            $dateRanges = $this->dateRanges($timezone);

            foreach( $dateRanges as $idx => $dateRange ){
                $dailyCounts = [];
                $startDay    = clone $dateRange['start'];

                for($i=0;$i<$days;$i++){
                    $dailyCounts[$startDay->format('Y-m-d')] = 0;
                    $startDay->modify('+1 day');
                }

                $results = DB::table('calls')
                            ->select([DB::raw('COUNT(*) AS total'), DB::raw("DATE_FORMAT(CONVERT_TZ(calls.created_at,'UTC','" . $timezone->getName() . "'),'%Y-%m-%d') AS create_date") ])
                            ->where(DB::raw("CONVERT_TZ(calls.created_at,'UTC','" . $timezone->getName() . "')"), '>=', $dateRange['start']->format('Y-m-d H:i:s.u'))
                            ->where(DB::raw("CONVERT_TZ(calls.created_at,'UTC','" . $timezone->getName() . "')"), '<=', $dateRange['end']->format('Y-m-d H:i:s.u'))
                            ->where('calls.company_id', $this->company_id)
                            ->groupBy('create_date')
                            ->orderBy('total', $this->order)
                            ->get();
                foreach( $results as $result ){
                    $dailyCounts[$result->create_date] = $result->total ?: 0;
                }
                

                $dataLabel = $dateRange['start']->format('M jS, Y') . ' - ' . $dateRange['end']->format('M jS, Y');
                $dataLabels = array_map(function($date){
                    $date = new DateTime($date);
                    return $date->format('M jS, Y');
                },array_keys($dailyCounts));

                $datasets[] = [
                    'label'         => $dataLabel,
                    'data'          => array_values($dailyCounts),
                    'data_labels'   => $dataLabels
                ];
            }
        }

        return $datasets;
    }

    public function dayDatasets($timezone)
    {
        $datasets   = [];

        if( $this->module == 'calls' ){
            $dateRanges = $this->dateRanges($timezone);

            foreach( $dateRanges as $idx => $dateRange ){
                $results = DB::table('calls')
                            ->select([DB::raw('COUNT(*) AS total'), DB::raw("DATE_FORMAT(CONVERT_TZ(calls.created_at,'UTC','" . $timezone->getName() . "'),'%H') AS create_date") ])
                            ->where(DB::raw("CONVERT_TZ(calls.created_at,'UTC','" . $timezone->getName() . "')"), '>=', $dateRange['start']->format('Y-m-d H:i:s.u'))
                            ->where(DB::raw("CONVERT_TZ(calls.created_at,'UTC','" . $timezone->getName() . "')"), '<=', $dateRange['end']->format('Y-m-d H:i:s.u'))
                            ->where('calls.company_id', $this->company_id)
                            ->groupBy('create_date')
                            ->orderBy('total', $this->order)
                            ->get();

                 //  Group items by their hour
                $hourlyCounts  = [];
                for($i=0;$i<24;$i++){
                    $hourlyCounts[str_pad($i,2,'0',STR_PAD_LEFT)] = 0;
                }
                
                foreach( $results as $result ){
                    $hourlyCounts[$result->create_date] = $result->total;
                }

                $dataLabel = $dateRange['start']->format('M d, Y');
                $datasets[] = [
                    'label' => $dataLabel,
                    'data'  => array_values($hourlyCounts),
                ];
            }
        }
        
        return $datasets;
    }

    public function yearDatasets($timezone)
    {
        $datasets = [];

        if( $this->module == 'calls' ){
            $dateRanges = $this->dateRanges($timezone);

            foreach( $dateRanges as $idx => $dateRange ){
                $results = DB::table('calls')
                            ->select([DB::raw('COUNT(*) AS total'), DB::raw("DATE_FORMAT(CONVERT_TZ(calls.created_at,'UTC','" . $timezone->getName() . "'),'%Y-%m') AS create_date") ])
                            ->where(DB::raw("CONVERT_TZ(calls.created_at,'UTC','" . $timezone->getName() . "')"), '>=', $dateRange['start']->format('Y-m-d H:i:s.u'))
                            ->where(DB::raw("CONVERT_TZ(calls.created_at,'UTC','" . $timezone->getName() . "')"), '<=', $dateRange['end']->format('Y-m-d H:i:s.u'))
                            ->where('calls.company_id', $this->company_id)
                            ->groupBy('create_date')
                            ->orderBy('total', $this->order)
                            ->get();

                 //  Group items by their hour
                $monthlyCounts  = [];
                $startDay       = clone $dateRange['start'];
                for($i=0;$i<12;$i++){
                    $monthlyCounts[$startDay->format('Y-m')] = 0;
                    $startDay->modify('+1 month');
                }
                
                foreach( $results as $result ){
                    $monthlyCounts[$result->create_date] = $result->total;
                }

                $dataLabel = $dateRange['start']->format('Y');
                $datasets[] = [
                    'label' => $dataLabel,
                    'data'  => array_values($monthlyCounts),
                ];
            }
        }
        
        return $datasets;
    }

    public function allTimeDatasets($timezone)
    {
        $datasets      = [];
        $allTimeCounts = [];
        $labelData     = $this->allTimeLabels($timezone);
        $dateRanges    = $this->dateRanges($timezone);

        $dateFormat = str_replace(['M','Y','y'], ['%b','%Y','%y'], $labelData['date_format']);

        if( $this->module === 'calls' ){
            $results = DB::table('calls')
                        ->select([DB::raw('COUNT(*) AS total'), DB::raw("DATE_FORMAT(CONVERT_TZ(calls.created_at,'UTC','" . $timezone->getName() . "'),'" . $dateFormat . "') AS create_date") ])
                        ->where(DB::raw("CONVERT_TZ(calls.created_at,'UTC','" . $timezone->getName() . "')"), '>=', $dateRanges[0]['start']->format('Y-m-d H:i:s.u'))
                        ->where(DB::raw("CONVERT_TZ(calls.created_at,'UTC','" . $timezone->getName() . "')"), '<=', $dateRanges[0]['end']->format('Y-m-d H:i:s.u'))
                        ->where('calls.company_id', $this->company_id)
                        ->groupBy('create_date')
                        ->orderBy('total', $this->order)
                        ->get();
        
            //  Fill counts with 0s
            foreach($labelData['labels'] as $label){
                $allTimeCounts[$label] = 0;
            }

            foreach( $results as $result ){
                $allTimeCounts[$result->create_date] = $result->total;
            }

            $datasets[] = [
                'label'         => $this->moduleLabel(),
                'data'          => array_values($allTimeCounts),
                'data_labels'   => array_fill(0, count($allTimeCounts), $this->moduleLabel())
            ];
        }

        return $datasets;
    }

    /**
     * Return previous date in user's time zone
     * 
     * @param int $offset
     * 
     * @return DateTime 
     */
    public function date($timezone, $offset)
    {
        $day            = new DateTime();
        $unit           = 'days';
        $subtract       = 0;

        switch( $this->date_unit ){
            case 'DAYS':
                $subtract = $offset;
            break;

            //  Do not include day
            case '7_DAYS':
                $subtract = (7 * $offset);
            break;

            case '14_DAYS':
                $subtract = (14 * $offset);
            break;

            case '28_DAYS':
                $subtract = (28 * $offset);
            break;

            case '60_DAYS':
                $subtract = (60 * $offset);
            break;

            case '90_DAYS':
                $subtract = (90 * $offset);
            break;

            case 'YEARS':
                $subtract = $offset;
                $unit     = 'years'; 
            break;

            default: 
            break;
        }

        $day->setTimeZone($timezone);
        $day->modify('-' . $subtract . ' ' . $unit);

        return $day;
    }

    /**
     * Get a formatted start date in the user's time zone
     *
     * @param int $offset
     * 
     * @return DateTime 
     */
    public function startDate($timezone, $offset)
    {
        $startDate = $this->date($timezone, $offset);

        switch( $this->date_unit ){
            case 'DAYS':
            case '7_DAYS':
            case '14_DAYS':
            case '28_DAYS':
            case '60_DAYS':
            case '90_DAYS':
                return new DateTime($startDate->format('Y-m-d 00:00:00'), $timezone);
            case 'YEARS':
                return new DateTime($startDate->format('Y-01-01 00:00:00'), $timezone);
            default: 
                return new DateTime($startDate->format('Y-m-d 00:00:00'), $timezone);
            break;
        }
    }

    /**
     * Get a formatted end date in the user's time zone
     *
     * @param int $offset
     * 
     * @return DateTime 
     */
    public function endDate($timezone, $offset, $startDate = null)
    {
        $endDate = $startDate ? (clone $startDate) : $this->startDate($timezone, $offset);

        switch( $this->date_unit ){
            case 'DAYS':
                $endDate->modify('+1 day');
            break;

            case '7_DAYS':
                $endDate->modify('+7 days');
            break;

            case '14_DAYS':
                $endDate->modify('+14 days');
            break;

            case '28_DAYS':
                $endDate->modify('+28 days');
            break;

            case '60_DAYS':
                $endDate->modify('+60 days');
            break;

            case '90_DAYS':
                $endDate->modify('+90 days');
            break;

            case 'YEARS':
                $endDate->modify('+1 year');
            break;

            default: 
            break;
        }

        $endDate->modify('-1 ms');

        return $endDate;
    }

    /**
     * Determine the time range for sutom dates
     * 
     */
    public function customDateRangeDays()
    {
        $dateRanges = $this->date_ranges;
        if( ! count($dateRanges) )
            return 0;
        
        $dateRange = explode(':', $dateRanges[0]);
        $start     = new DateTime($dateRange[0]);
        $end       = new DateTime($dateRange[1]);
        $end->modify('+1 day');

        return $start->diff($end)->days;
    }

    /**
     * Determine if an instance is associated with a metric
     * 
     */
    public function hasMetric()
    {
        return $this->metric ? true : false;
    }

    /**
     * Determine if a metric exists for a module
     * 
     */
    static public function metricExists($module, $metric)
    {
        return isset(self::$metrics[$module]) && in_array($metric, array_keys(self::$metrics[$module]));
    }

    /**
     * Get a list of chart types that are available when a metric is provided
     * 
     */
    static public function metricChartTypes()
    {
        return [
            self::CHART_TYPE_BAR,
            //self::CHART_TYPE_PIE,
            //self::CHART_TYPE_DOUGHNUT,
        ];
    }

    /**
     * Get a list of all chart types
     * 
     */
    static public function chartTypes()
    {
        return array_merge([
            self::CHART_TYPE_LINE,
        ], self::metricChartTypes());
    }

    /**
     * Check if a chart type exists
     * 
     */
    static public function chartTypeExists($chartType)
    {
        return in_array($chartType, self::chartTypes());
    }
}
