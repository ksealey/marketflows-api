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
        'range_type',
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

    protected $reportFields    = [
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
        ]
    ];

    protected $metrics = [
        'calls' => [
            'phone_number_name' => 'Phone Number'
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
                "labels"   => $this->labels(),
                "datasets" => $this->datasets()
            ],
            "metric_label" => 'Calls',
            "step_size"    => 10,
            "type"         => $this->hasMetric() ? 'bar' : 'line'
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

    public function label()
    {
        return 'Calls';
    }

    public function labels()
    {
        switch( $this->range_type ){
            case 'YEARS':
                return $this->yearLabels();
            case 'MONTHS':
                return $this->monthLabels();
            case 'DAYS':
                return $this->dayLabels();
            default:
                return $this->customLabels();
        }
    }

    public function datasets()
    {
        switch( $this->range_type ){
            case 'YEARS':
                return $this->yearDataset();
            case 'MONTHS':
                return $this->monthDataset();
            case 'DAYS':
                return $this->dayDataset();
            default:
                return $this->customDataset();
        }
    }

    //
    //  Dates  
    //
    /**
     * Return previous date in user's time zone
     * 
     */
    public function date($offset)
    {
        $modifier = strtolower($this->range_type);
        $day      = new DateTime();
        $day->setTimeZone($this->timezone);
        $day->modify('-' . $offset . ' ' . $modifier);

        return $day;
    }

    public function startDate($offset)
    {
        $startDate = $this->date($offset);

        switch( $this->range_type ){
            case 'YEARS':
                $startDate = new DateTime($startDate->format('Y-01-01 00:00:00'), $this->timezone);
            break;

            case 'MONTHS':
                $startDate = new DateTime($startDate->format('Y-m-01 00:00:00'), $this->timezone);
            break;

            default: 
                $startDate = new DateTime($startDate->format('Y-m-d 00:00:00'), $this->timezone);
            break;
        }

        return $startDate;
    }

    public function endDate($offset)
    {
        $endDate = $this->date($offset);

        switch( $this->range_type ){
            case 'YEARS':
                $endDate = new DateTime($endDate->format('Y-12-31 23:59:59'), $this->timezone);
            break;

            case 'MONTHS':
                $endDate = new DateTime($endDate->format('Y-m-' . $endDate->format('t') . ' 23:59:59'), $this->timezone);
            break;

            default: 
                $endDate = new DateTime($endDate->format('Y-m-d 23:59:59'), $this->timezone);;
            break;
        }

        return $endDate;
    }

    /**
     * Determine labels for days.
     * 
     */
    public function dayLabels()
    {
        if( $this->hasMetric() ){
            //  Use date ranges as bottom labels
            $labels = [];
            foreach( $this->resultSets as $resultSet ){
                $labels[] = $resultSet['dates']['start']->format('M d, Y');
            }
            return $labels;
        }else{
            //  Use times in day as bottom labels
            return [
                '12-1AM', '1-2AM', '2-3AM', '3-4AM', '4-5AM', '5-6AM', '6-7AM', '7-8AM', '8-9AM', '9-10AM', '10-11AM', '11AM-12PM', 
                '12-1PM', '1-2PM', '2-3PM', '3-4PM', '4-5PM', '5-6PM', '6-7PM', '7-8PM', '8-9PM', '9-10PM', '10-11PM', '11PM-12AM'
            ];
        }
    }


    /**
     * Determine labels for months
     * 
     */
    public function monthLabels()
    {

    }

    /**
     * Determine labels for years
     * 
     */
    public function yearLabels()
    {

    }

    /**
     * Determine labels for custom date ranges
     * 
     */
    public function customLabels()
    {

    }


    /**
     * Determine data for days. Days are broken down by hour.
     * 
     */
    public function dayDataset()
    {
        $datasets = [];

        if( $this->hasMetric() ){
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

        }else{
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
        }
        
        return $datasets;
    }

    /**
     * Determine data for months
     * 
     */
    public function monthData()
    {

    }

    /**
     * Determine data for years
     * 
     */
    public function yearData()
    {

    }

    /**
     * Determine data for custom date ranges
     * 
     */
    public function customData()
    {

    }

    public function hasMetric()
    {
        return $this->metric ? true : false;
    }

    static public function metricExists($module, $metric)
    {
        return isset($this->metrics[$module]) && in_array($metric, $this->metrics[$module]);
    }

    static public function metricLabel($metric)
    {
        return $this->getMetrics()[$metric] ?? null;
    }
}
