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

    protected $utcTimezone;

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

    public function getChartAttribute()
    {
        //  Run report
        $this->utcTimezone = new DateTimeZone('UTC');

        $results = $this->run('America/New_York');

        return [
            "data" => [
                "labels"   => $this->dataLabels(),
                "datasets" => $this->datasets()
            ],
            "module_label" => $this->moduleLabel(),
            "metric_label" => $this->metricLabel(),
            "range_label"  => $this->rangeLabel(),
            "type"         => $this->hasMetric() ? $this->chart_type : self::CHART_TYPE_LINE,
            "step_size"    => 10,
        ];
    }

    public function getResultsAttribute()
    {
        //  Flatten the data
        $results = [];
        foreach( $this->resultSets as $resultSet ){
            $results = array_merge($results, $resultSet['data']);
        }
        return $results;
    }

    public function withChart()
    {
        $this->chart = $this->chart;

        return $this;
    }

    public function run(string $timezone)
    {
        $this->timezone = new DateTimeZone($timezone);

        if( $this->module === 'calls' )
            return $this->runCallReport();

        return null;
    }

    public function runCallReport()
    {
        $dateSets = [];
        if( $this->date_unit === 'CUSTOM' ){
            $dateRanges = $this->date_ranges;
            foreach( $dateRanges as $dateRange ){
                $dates      = explode(':', $dateRange);
                $startDate  = new DateTime($dates[0] . ' 00:00:00.000000', $this->timezone); 
                $endDate    = new DateTime($dates[1] . ' 23:59:59.999999', $this->timezone); 
                $dateSets[] = [
                    'start' => $startDate,
                    'end'   => $endDate
                ];
            }
        }elseif( $this->date_unit === 'ALL_TIME' ){
            $startDate  = new DateTime('1970-01-01 00:00:00'); 
            $endDate    = new DateTime();

            $startDate->setTimeZone($this->timezone); 
            $endDate->setTimeZone($this->timezone); 

            $dateSets[] = [
                'start' => $startDate,
                'end'   => $endDate
            ];
        }else{
            $offsets = $this->date_offsets;
            foreach( $offsets as $offset ){
                $dateSets[] = [
                    'start' => $this->startDate($offset),
                    'end'   => $this->endDate($offset)
                ];
            }
        }

        //  Order based on date order
        $dateOrder = $this->order;
        usort($dateSets, function($a, $b)use($dateOrder){
            if( $dateOrder === 'min' ){
                return $a['start']->format('U') > $b['start']->format('U') ? 1 : -1;
            }else{
                return $a['start']->format('U') > $b['start']->format('U') ? -1 : 1;
            }
        });

        $resultSets = [];
        foreach( $dateSets as $dateSet ){
            $startDateUTC = clone $dateSet['start'];
            $endDateUTC   = clone $dateSet['end'];

            $startDateUTC->setTimeZone($this->utcTimezone);
            $endDateUTC->setTimeZone($this->utcTimezone);

            $results = DB::table('calls')
                            ->select([
                                'calls.*',
                                'phone_numbers.name AS phone_number_name', 
                                'phone_number_pools.name as phone_number_pool_name'
                            ])
                            ->leftJoin('phone_numbers', 'phone_numbers.id', 'calls.phone_number_id')
                            ->leftJoin('phone_number_pools', 'phone_number_pools.id', 'calls.phone_number_pool_id')
                            ->where('calls.created_at', '>=', $startDateUTC)
                            ->where('calls.created_at', '<=', $endDateUTC)
                            ->where('calls.company_id', $this->company_id)
                            ->orderBy('calls.created_at', 'ASC')
                            ->get();

            $resultSets[] = [
                'data'  => $results,
                'dates' => $dateSet
            ];
        }

        $this->resultSets = $resultSets;

        return $this->resultSets;
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

    public function dataLabels()
    {
        if( $this->hasMetric() )
            return $this->metricLabels();

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
                return $this->dayLabels($this->getCustomDateRangeDays());
            case 'ALL_TIME':
                return $this->allTimeLabels()['labels'];
            default:
                return [];
            break;
        }
    }

    /**
     * Labels
     * 
     */
    public function metricLabels()
    {
        //  Use date ranges as bottom labels
        $labels = [];
        foreach( $this->resultSets as $resultSet ){
            
            switch( $this->date_unit ){
                case 'YEARS':
                    $labels[] = $resultSet['dates']['start']->format('Y');
                break;

                case 'ALL_TIME':
                    $labels[] = $this->moduleLabel() . ' by ' . $this->metricLabel();
                break;

                default: 
                    $start = $resultSet['dates']['start']->format('M d, Y');
                    $end   = $resultSet['dates']['end']->format('M d, Y');
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

    public function allTimeLabels()
    {
        //  If there are no results, return current year
        if( ! count($this->resultSets) )
            return [
                'labels'     => [date('Y')],
                'group_type' => 'YEARS',
                'date_format'=> 'Y'
            ];

        $groupType  = null;
        $dateFormat = null;
        $labels     = [];
        $results    = $this->resultSets[0]['data'];

        $first = new DateTime($results[0]->created_at);
        $first->setTimeZone($this->timezone);

        $last = new DateTime($results[count($results) - 1]->created_at);
        $last->setTimeZone($this->timezone);

        $diff = $first->diff($last);
       
        if( $diff->y > 1 ){
            $groupType = 'YEARS';
            $dateFormat = 'Y';
            while( $first->format('Y') <= $last->format('Y') ){
                $labels[] = $first->format($dateFormat);
                $first->modify('+1 year');
            }
        }else{
            $groupType  = 'MONTHS';
            $dateFormat = 'M, y';
            while( $first->format('Ym') <= $last->format('Ym') ){
                $labels[] = $first->format($dateFormat);
                $first->modify('+1 month');
            }
        }

        return [
            'labels'      => $labels,
            'group_type'  => $groupType,
            'date_format' => $dateFormat
        ];
    }

    public function allTimeFirstLast()
    {
        // Get date of first and last record
        $results = $this->resultSets[0]['data'];
        $first   = new DateTime($results[0]->created_at);
        $last    = new DateTime($results[count($results) - 1]->created_at);
        $diff    = $first->diff($last);

        
        if( $diff->y > 1 ){
            return 'YEARS';
        }else{
            return 'MONTHS';
        }
    }


    /**
     * Datasets
     * 
     */
    public function datasets()
    {
        if( $this->hasMetric() )
            return $this->metricDatasets();

        switch( $this->date_unit ){
            case 'DAYS':
                return $this->dayDatasets();
            case '7_DAYS':
                return $this->dayRangeDatasets(7);
            case '14_DAYS':
                return $this->dayRangeDatasets(14);
            case '28_DAYS':
                return $this->dayRangeDatasets(28);
            case '60_DAYS':
                return $this->dayRangeDatasets(60);
            case '90_DAYS':
                return $this->dayRangeDatasets(90);
            case 'YEARS':
                return $this->yearDatasets();
            case 'CUSTOM':
                return $this->dayRangeDatasets($this->getCustomDateRangeDays());
            case 'ALL_TIME':
                return $this->allTimeDatasets();
            default:
                break;
        }
    }
    
    /**
     * Get a dataset for a report that has a metric
     * 
     * @return array
     */
    public function metricDatasets()
    {
        $datasets       = [];
        $metricKey      = explode('.', $this->metric);
        $metricKey      = end($metricKey);
        $metricCounts   = [];

        //  Get a count of all values for this metric
        foreach( $this->resultSets as $resultSet ){
            foreach( $resultSet['data'] as $result ){
                if( ! isset($metricCounts[$result->$metricKey]) )
                    $metricCounts[$result->$metricKey] = 0;
                $metricCounts[$result->$metricKey]++;
            }
        }
        uasort($metricCounts, function($a, $b){
            return $a > $b ? -1 : 1;
        });

        //  Go through all result sets to attribute metric
        $loop   = 0;
        $others = [];
        $handledMetrics = [];
        foreach( $metricCounts as $metricValue => $metricCount ){
            ++$loop;
            if( $loop >= 15 )
                break;

            $dataset = [
                'label' => $metricValue,
                'data'  => []
            ];

            foreach( $this->resultSets as $resultSet ){
                $foundCount = 0;
                foreach( $resultSet['data'] as $result ){
                    if( $result->$metricKey == $metricValue )
                        $foundCount++;
                }
                $dataset['data'][] = $foundCount;
            }
            $datasets[] = $dataset;
            $handledMetrics[] = $metricValue;
        }

        //  Group the rest as "Other"
        if( $loop >= 15 ){
            $dataset = [
                'label' => 'Other',
                'data'  => []
            ];

            foreach( $this->resultSets as $resultSet ){
                $otherCount = 0;
                foreach( $metricCounts as $metricValue => $metricCount ){
                    if( in_array($metricValue, $handledMetrics) )
                        continue;

                    foreach( $resultSet['data'] as $result ){
                        if( $result->$metricKey == $metricValue )
                            $otherCount++;
                    }
                }
                $dataset['data'][] = $otherCount;
            };

            $datasets[] = $dataset;
        }

        return $datasets;
    }

    public function dayRangeDatasets($days)
    {
        $datasets = [];
        
        foreach( $this->resultSets as $resultSet ){
            $dailyCounts = [];
            $startDay    = clone $resultSet['dates']['start'];
            for($i=0;$i<$days;$i++){
                $dailyCounts[$startDay->format('M jS, Y')] = 0;
                $startDay->modify('+1 day');
            }
            
            foreach( $resultSet['data'] as $result ){
                $createdAt = new DateTime($result->created_at);
                $createdAt->setTimeZone($this->timezone);

                $dailyCounts[$createdAt->format('M jS, Y')] += 1;
            }
            
            $dataLabel   = $resultSet['dates']['start']->format('M jS, Y') . ' - ' . $resultSet['dates']['end']->format('M jS, Y');
            $datasets[] = [
                'label'         => $dataLabel,
                'data'          => array_values($dailyCounts),
                'data_labels'   => array_keys($dailyCounts)
            ];
        }
        
        return $datasets;
    }

    public function dayDatasets()
    {
        $datasets = [];

        //  Group items by their hour
        foreach( $this->resultSets as $resultSet ){
            $hourlyCounts  = [];
            for($i=0;$i<24;$i++){
                $hourlyCounts[str_pad($i,2,'0',STR_PAD_LEFT)] = 0;
            }
            
            foreach( $resultSet['data'] as $result ){
                $createdAt = new DateTime($result->created_at);
                $createdAt->setTimeZone($this->timezone);

                $hour                = $createdAt->format('H');
                $hourlyCounts[$hour] += 1;
            }

            $dataLabel = $resultSet['dates']['start']->format('M d, Y');
            $datasets[] = [
                'label' => $dataLabel,
                'data'  => array_values($hourlyCounts),
            ];
        }
        
        return $datasets;
    }

    public function yearDatasets()
    {
        $datasets = [];

        //  Group items by month
        foreach( $this->resultSets as $resultSet ){
            $yearLabels    = [];
            $monthlyCounts = [];
            
            //  Fill with all 12 months
            $startDay  = clone $resultSet['dates']['start'];
            for($i=0;$i<12;$i++){
                $monthlyCounts[$startDay->format('M')] = 0;
                $yearLabels[] = $startDay->format('Y');
                $startDay->modify('+1 month');
            }
            
            //  Group result counts by month
            foreach( $resultSet['data'] as $result ){
                $createdAt = new DateTime($result->created_at);
                $createdAt->setTimeZone($this->timezone);

                $monthlyCounts[$createdAt->format('M')] += 1;
            }
            
            $dataLabel   = $resultSet['dates']['start']->format('Y');
            $datasets[] = [
                'label'         => $dataLabel,
                'data'          => array_values($monthlyCounts),
                'data_labels'   => $yearLabels
            ];
        }
        
        return $datasets;
    }

    public function allTimeDatasets()
    {
        $datasets      = [];
        $allTimeCounts = [];
        $labelData     = $this->allTimeLabels();

        //  Fill counts with 0s
        foreach($labelData['labels'] as $label){
            $allTimeCounts[$label] = 0;
        }

        $resultSet = $this->resultSets[0];
        foreach($labelData['labels'] as $label){
            foreach( $resultSet['data'] as $result ){
                $createdAt = new DateTime($result->created_at);
                $createdAt->setTimeZone($this->timezone);
                if( $createdAt->format($labelData['date_format']) === $label )
                    $allTimeCounts[$createdAt->format($labelData['date_format'])]++;
            }
        }

        $datasets[] = [
            'label'         => $this->moduleLabel(),
            'data'          => array_values($allTimeCounts),
            'data_labels'   => array_fill(0, count($allTimeCounts), $this->moduleLabel())
        ];

        return $datasets;
    }

    /**
     * Return previous date in user's time zone
     * 
     * @param int $offset
     * 
     * @return DateTime 
     */
    public function date($offset)
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

        $day->setTimeZone($this->timezone);
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
    public function startDate($offset)
    {
        $startDate = $this->date($offset);

        switch( $this->date_unit ){
            case 'DAYS':
            case '7_DAYS':
            case '14_DAYS':
            case '28_DAYS':
            case '60_DAYS':
            case '90_DAYS':
                return new DateTime($startDate->format('Y-m-d 00:00:00'), $this->timezone);
            case 'YEARS':
                return new DateTime($startDate->format('Y-01-01 00:00:00'), $this->timezone);
            default: 
                return new DateTime($startDate->format('Y-m-d 00:00:00'), $this->timezone);
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
    public function endDate($offset, $startDate = null)
    {
        $endDate = $startDate ? (clone $startDate) : $this->startDate($offset);

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
    public function getCustomDateRangeDays()
    {
        $dateRanges = $this->date_ranges;
        if( ! count($dateRanges) )
            return 0;
        
        $dateRange = explode(':', $dateRanges[0]);
        $start     = new DateTime($dateRange[0]);
        $end       = new DateTime($dateRange[1]);

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
