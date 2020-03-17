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
        'fields',
        'metric',
        'metric_order',
        'date_unit',
        'date_offsets',
        'is_system_report'
    ];

    protected $appends = [
        'link',
        'kind',
        'graph'
    ];

    protected $utcTimezone;

    protected $timezone;

    protected $resultSets = [];

    static protected $moduleLabels  = [
        'calls' => 'Calls'
    ];

    static protected $reportFields    = [
        'calls' => [
            'calls.company_id',
            'calls.phone_number_id',
            'phone_number_name',
            'calls.toll_free',
            'calls.category',
            'calls.sub_category',
            'calls.phone_number_pool_id',
            'phone_number_pool_name',
            'calls.session_id',
            'calls.direction',
            'calls.status',
            'calls.caller_first_name',
            'calls.caller_last_name',
            'calls.caller_country_code',
            'calls.caller_number',
            'calls.caller_city',
            'calls.caller_state',
            'calls.caller_zip',
            'calls.caller_country',
            'calls.dialed_country_code',
            'calls.dialed_number',
            'calls.dialed_city',
            'calls.dialed_state',
            'calls.dialed_zip',
            'calls.dialed_country',
            'calls.source',
            'calls.medium',
            'calls.content',
            'calls.campaign',
            'calls.recording_enabled',
            'calls.caller_id_enabled',
            'calls.forwarded_to',
            'calls.duration',
            'calls.created_at',
            'calls.*'
        ]
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

    public function getDateOffsetsAttribute($dateOffset)
    {
        return json_decode($dateOffset);
    }

    public function getFieldsAttribute($fields)
    {
        return json_decode($fields);
    }

    public function getGraphAttribute()
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
            "type"         => $this->hasMetric() ? 'bar' : 'line',
            "step_size"    => 10,
        ];
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
        $fields = array_merge($this->fields, [
            'phone_numbers.name AS phone_number_name', 
            'phone_number_pools.name as phone_number_pool_name',
            'calls.created_at'
        ]);

        $resultSets = [];

        $offsets = $this->date_offsets;
        usort($offsets, function($a,$b){ return $a > $b ? -1 : 1; });

        foreach( $offsets as $offset ){
            $startDate = $this->startDate($offset);
            $endDate   = $this->endDate($offset);

            $startDateUTC = clone $startDate;
            $endDateUTC   = clone $endDate;

            $startDateUTC->setTimeZone($this->utcTimezone);
            $endDateUTC->setTimeZone($this->utcTimezone);

            $results = DB::table('calls')
                            ->select($fields)
                            ->leftJoin('phone_numbers', 'phone_numbers.id', 'calls.phone_number_id')
                            ->leftJoin('phone_number_pools', 'phone_number_pools.id', 'calls.phone_number_pool_id')
                            ->where('calls.created_at', '>=', $startDateUTC)
                            ->where('calls.created_at', '<=', $endDateUTC)
                            ->where('calls.company_id', $this->company_id)
                            ->orderBy('calls.created_at', 'ASC')
                            ->get();

            $resultSets[] = [
                'data'  => $results,
                'dates' => [
                    'start' => $startDate,
                    'end'   => $endDate
                ]
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

    public function rangeLabel()
    {
        if( $this->hasMetric() )
            return null;

        switch( $this->date_unit ){
            case 'DAYS':
            case 'YEARS':
            case 'CUSTOM':
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
                return $this->dayLabels();
            case '7_DAYS':
                return $this->dateLabels(7);
            case '14_DAYS':
                return $this->dateLabels(14);
            case '28_DAYS':
                return $this->dateLabels(28);
            case '60_DAYS':
                return $this->dateLabels(60);
            case '90_DAYS':
                return $this->dateLabels(90);
            case 'YEARS':
                return $this->yearLabels();
            default:
                return $this->customLabels();
        }
    }


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
            default:
                return $this->customDatasets();
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
            $start = $resultSet['dates']['start']->format('M d, Y');
            $end   = $resultSet['dates']['end']->format('M d, Y');
            $labels[] = $start . ( $start == $end ? '' : (' - ' . $end));
        }
        return $labels;
    }

    public function dateLabels($days)
    {
        $labels    = [];
        for( $i=1; $i<=$days; $i++ ){
            $labels[] = $i;
        }
        return $labels;
    }

    public function dayLabels()
    {
        return [
            '12-1AM', '1-2AM', '2-3AM', '3-4AM', '4-5AM', '5-6AM', '6-7AM', '7-8AM', '8-9AM', '9-10AM', '10-11AM', '11AM-12PM', 
            '12-1PM', '1-2PM', '2-3PM', '3-4PM', '4-5PM', '5-6PM', '6-7PM', '7-8PM', '8-9PM', '9-10PM', '10-11PM', '11PM-12AM'
        ];
    }

    public function yearLabels()
    {
        return [
            'January', 'February', 'March', 'April', 'May', 'June', 
            'July', 'August', 'September', 'October', 'November', 'December' 
        ];
    }

    public function customLabels()
    {

    }



    /**
     * Datasets
     * 
     */
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
            $monthlyCounts = [];
            $startDay      = clone $resultSet['dates']['start'];
            for($i=0;$i<12;$i++){
                $monthlyCounts[$startDay->format('M')] = 0;

                $startDay->modify('+1 month');
            }
            
            foreach( $resultSet['data'] as $result ){
                $createdAt = new DateTime($result->created_at);
                $createdAt->setTimeZone($this->timezone);

                $monthlyCounts[$createdAt->format('M')] += 1;
            }
            
            $dataLabel   = $resultSet['dates']['start']->format('Y');
            $datasets[] = [
                'label'         => $dataLabel,
                'data'          => array_values($monthlyCounts),
                'data_labels'   => array_keys($monthlyCounts)
            ];
        }
        
        return $datasets;
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
     * Determine if a metric exists for a module
     * 
     */
    static public function fieldExists($module, $field)
    {
        return isset(self::$reportFields[$module]) && in_array($field, self::$reportFields[$module]);
    }
}
