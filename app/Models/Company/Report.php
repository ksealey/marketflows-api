<?php

namespace App\Models\Company;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Company\ReportAutomation;
use League\Flysystem\Adapter\Local;
use League\Flysystem\Filesystem;
use Cache\Adapter\Filesystem\FilesystemCachePool;
use Cache\Bridge\SimpleCache\SimpleCacheBridge;
use App\Traits\AppliesConditions;
use \App\Traits\PerformsExport;
use Spreadsheet;
use Xlsx;
use Worksheet;
use SpreadsheetSettings;
use DB;
use DateTime;
use DateTimeZone;

class Report extends Model
{
    use AppliesConditions, PerformsExport, SoftDeletes;

    protected $fillable = [
        'account_id',
        'company_id',
        'created_by',
        'updated_by',
        'deleted_by',
        'name',
        'module',
        'metric',
        'metric_order',
        'timezone',
        'date_type',
        'comparisons',
        'conditions',
        'start_date',
        'end_date',
        'is_system_report'
    ];

    protected $hidden = [
        'deleted_by',
        'deleted_at'
    ];

    protected $appends = [
        'link',
        'kind'
    ];

    protected $allTimeStart = '';

    protected $dataOptions = [];

    static protected $moduleLabels  = [
        'calls' => 'Calls'
    ];

    static protected $metrics = [
        'calls' => [
            'calls.source'          => 'Source',
            'calls.medium'          => 'Medium',
            'calls.campaign'        => 'Campaign',
            'calls.content'         => 'Content',
            'calls.category'        => 'Category',
            'calls.sub_category'    => 'Sub-Category',
            'calls.caller_city'     => 'Caller City',
            'calls.caller_state'    => 'Caller State',
            'calls.caller_zip'      => 'Caller Zip',
            'phone_numbers.name'    => 'Dialed Phone Number'
        ]
    ];

    static protected $exposedFields = [
        'calls' => [
            'fields' => [
                'calls.id',
                'calls.company_id',
                'companies.name',
                'calls.type',
                'calls.category',
                'calls.sub_category',
                'calls.phone_number_pool_id',
                'phone_number_pools.name',
                'calls.phone_number_id',
                'phone_numbers.name',
                'calls.caller_name',
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
                'calls.forwarded_to',
                'calls.direction',
                'calls.status',
                'calls.duration',
                'calls.recording_enabled',
                'calls.created_at'
            ],
            'aliases' => [
                'phone_numbers.name'        => 'phone_number_name',
                'phone_number_pools.name'   => 'phone_number_pool_name',
                'companies.name'            => 'company_name'
            ],
            //  IMORTANT: Keep in order for export headers
            'headers' => [
                'id'                        => 'Call Id',
                'company_id'                => 'Company Id',
                'company_name'              => 'Company',
                'type'                      => 'Toll-Free',
                'category'                  => 'Category',
                'sub_category'              => 'Sub-Category',
                'phone_number_pool_id'      => 'Keyword Tracking Pool Id',
                'phone_number_pool_name'    => 'Keyword Tracking Pool',
                'phone_number_id'           => 'Tracking Number Id',
                'phone_number_name'         => 'Tracking Number',
                'caller_name'               => 'Caller Name',
                'caller_country_code'       => 'Caller Country Code',
                'caller_number'             => 'Caller Number',
                'caller_city'               => 'Caller City',
                'caller_state'              => 'Caller State',
                'caller_zip'                => 'Caller Zip',
                'caller_country'            => 'Caller Country',
                'source'                    => 'Caller Source',
                'medium'                    => 'Caller Medium',
                'content'                   => 'Caller Content',
                'campaign'                  => 'Caller Campaign',
                'forwarded_to'              => 'Forwarded To',
                'direction'                 => 'Direction',
                'status'                    => 'Status',
                'duration'                  => 'Duration',
                'recording_enabled'         => 'Recording Enabled',
                'created_at'                => 'Call Time'
            ]
        ]
    ];

    static public function exports() : array
    {
        return [
            'id'                => 'Id',
            'company_id'        => 'Company Id',
            'name'              => 'Name',
            'created_at'        => 'Created'
        ];
    }

    static public function exportFileName($user, array $input) : string
    {
        return 'Reports - ' . $input['company_name'];
    }

    static public function exportQuery($user, array $input)
    {
        return Report::where('reports.company_id', $input['company_id']);
    }

    /**
     * Attributes
     * 
     */
    public function getLinkAttribute()
    {
        return route('read-report', [
            'company' => $this->company_id,
            'report'  => $this->id
        ]);
    }

    public function getKindAttribute()
    {
        return 'Report';
    }

    public function getConditionsAttribute($conditions)
    {
        if( ! $conditions ) return [];

        return json_decode($conditions);
    }

    public function getComparisonsAttribute($comparisons)
    {
        if( ! $comparisons ) return [];

        return json_decode($comparisons);
    }

    /**
     * Relationships
     * 
     */
    public function automations()
    {
        return $this->hasMany(ReportAutomation::class);
    }

    /**
     * Fetch a chart
     * 
     */
    public function charts()
    {
        //  Get the date unit
        $unit      = $this->timeUnit();
        $unitLabel = $this->timeUnitLabel($unit);
        
        //  Get the chart's type
        $chartType = $this->metric ? 'bar' : 'line';

        //  Get the chart's title
        $chartTitle  = $this->chartTitle();
    
        //  Get the charts labels
        $chartLabels = $this->chartLabels();

        //  Get step size
        $stepSize = $this->stepSize();

        //  Get the datasets
        $datasets = $this->datasets();
        
        return [
            'charts' => [
                [
                    'type'      => $chartType,
                    'title'     => $chartTitle,
                    'labels'    => $chartLabels,
                    'step_size' => $stepSize,
                    'datasets'  => $datasets,
                    'time'      => [
                        'unit'  => $unit,
                        'unitLabel' => $unitLabel
                    ],
                    'is_comparison' => $this->comparisons ? true : false,
                ]
            ]
        ]; 
    }

    public function chartTitle()
    {
        $chartTitle  = $this->moduleLabel();
        if( $this->metric )
            $chartTitle .= ' by ' . $this->metricLabel();
        if( $this->comparisons )
            $chartTitle .= ' (Comparison)';
        return $chartTitle;
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
        if( $this->metric && self::metricExists($this->module, $this->metric) )
            return self::$metrics[$this->module][$this->metric];

        return null;
    }

    public function chartLabels()
    {
        $timezone   = new DateTimeZone($this->timezone);
        $labels     = [];
        $timeUnit   = $this->timeUnit();
        $dateOffset = $this->dateOffset();

        if( $this->metric ){
            //  With metric
            $dateRanges  = $this->dateRanges();
            foreach( $dateRanges as $dateRange ){
                $label  = $dateRange['start']->format('M, j Y');
                if( $dateRange['start']->format('Y-m-d') !== $dateRange['end']->format('Y-m-d') ){
                    $label .= ' - ' . $dateRange['end']->format('M, j Y');
                }
                $labels[] = $label; 
            }
        }else{
            //  No metric
            if( $timeUnit === 'hour' ){
                //
                //  Same day
                //
                $start = new DateTime(date('Y-m-d') . '  00:00:00', $timezone);
                for( $i = 0; $i < 24; $i++){
                    $labels[] = $start->format('gA');
                    $start->modify('+1 hour');
                }
            }else{
                //  
                //  Days
                //
                if( $this->comparisons ){
                    for( $i = 0; $i <= $dateOffset; $i++ ){
                        $labels[] = $i + 1;
                    }
                }else{
                    $dateRange  = $this->dateRanges()[0];
                    $start      = clone $dateRange['start'];
                    $end        = clone $dateRange['end'];
                    $dateFormat = $start->format('Y') === $end->format('Y') ? 'M j' : 'M j, Y';
                    while( $start->format('U') <= $end->format('U') ){
                        $labels[] = $start->format($dateFormat);
                        $start->modify('+1 day');
                    }
                }
            }
        }

        return $labels;
    }

    public function stepSize()
    {
        return 10; // TODO: Actually calulate step size
    }

    /**
     * Get the amount of days between the current date range
     * 
     */
    public function dateOffset()
    {
        $timezone = new DateTimeZone($this->timezone);
        $offset   = 0;
        switch($this->date_type){
            case 'LAST_7_DAYS':  $offset = 7;  break;
            case 'LAST_14_DAYS': $offset = 14; break;
            case 'LAST_28_DAYS': $offset = 28; break;
            case 'LAST_30_DAYS': $offset = 30; break;
            case 'LAST_60_DAYS': $offset = 60; break;
            case 'LAST_90_DAYS': $offset = 90; break;
            case 'YEAR_TO_DATE': 
                $now = new DateTime();
                $now->setTimeZone($timezone);

                //  Get first day of year in user's timezone
                $firstDayOfYear = new DateTime(date('Y') . '-01-01 00:00:00', $timezone);
                $offset         = $now->diff($firstDayOfYear)->days;
                break;
            case 'CUSTOM':
                $startDate = new DateTime($this->start_date, $timezone);
                $endDate   = new DateTime($this->end_date, $timezone);

                $offset = $startDate->diff($endDate)->days;
                break;
            default:
                break;
        }
        return $offset;
    }

    public function timeUnit()
    {
        $dateOffset = $this->dateOffset();
        if( ! $dateOffset )
            return 'hour';
        return 'day';
    }

    public function timeUnitLabel($unit = null)
    {
        if( $this->metric )
            return '';

        if( ! $unit ) $unit = $this->timeUnit();
        
        return $unit === 'hour' ? 'Time' : 'Day';
    }

    /**
     * Convert a report's settings into date ranges, ordered from earliest to most recent
     * 
     */
    public function dateRanges()
    {
        $timezone   = new DateTimeZone($this->timezone);
        $dateSets   = [];
        $dateRanges = [];

        $offset     = $this->dateOffset();
        $utcTZ      = new DateTimeZone('UTC');
        $startDate  = $this->start_date ? new DateTime($this->start_date, $timezone) : null;
        $endDate    = $this->end_date   ? new DateTime($this->end_date, $timezone)   : null; 

        //  Add base date
        if( $this->date_type === 'ALL_TIME' ){
            if( ! $this->allTimeStart ){
                $result = DB::table($this->module)
                            ->select('created_at')
                            ->where('company_id', $this->company_id)
                            ->orderBy('created_at', 'ASC')
                            ->limit(1)
                            ->get();

                if( count($result) ){
                    $this->allTimeStart = new DateTime($result[0]->created_at);
                }else{
                    $this->allTimeStart = new DateTime();
                }
            }
            
            $startDate = $this->allTimeStart;
            $endDate   = new DateTime();

            $startDate->setTimeZone($timezone);
            $endDate->setTimeZone($timezone);
        }elseif ( $this->date_type === 'YEAR_TO_DATE' ){
            $startDate = new DateTime(date('Y') . '-01-01 00:00:00', $timezone);
            $endDate   = new DateTime();
            $endDate->setTimeZone($timezone);
        }elseif( stripos($this->date_type, 'LAST_') !== false ){
            $today     = new DateTime(date('Y-m-d ') . ' 00:00:00');
            $today->setTimeZone($timezone);

            $endDate   = (clone $today)->modify('-1 days');
            $startDate = (clone $endDate)->modify('- ' . ($offset - 1) . ' days');
        }

        $dateRanges[] = [
            $startDate,
            $endDate
        ];
        
        foreach( $this->comparisons as $comparison ){
            if( $this->date_type === 'ALL_TIME' ) continue;

            if( ! $offset ){
                $end = clone $startDate;
                $end->modify('-' . $comparison . ' days');
                $start  = clone $end;
            }else{
                $end = clone $endDate;
                $end->modify('-' . (($offset + 1) * $comparison) . ' days');
                $start = (clone $end)->modify('- ' . ($offset) . ' days');
            }

            $dateRanges[] = [
                $start,
                $end
            ];
        }

        foreach( $dateRanges as $dateRange ){
            $startDate  = new DateTime($dateRange[0]->format('Y-m-d') . ' 00:00:00.000000', $timezone); 
            $endDate    = new DateTime($dateRange[1]->format('Y-m-d')  . ' 23:59:59.999999', $timezone); 
            $dateSets[] = [
                'start' => $startDate,
                'end'   => $endDate
            ];
        }

        usort($dateSets, function($a, $b){
            return $a['start']->format('U') > $b['start']->format('U') ? 1 : -1;
        });

        return $dateSets;
    }


    /**
     * Datasets
     * 
     */
    public function datasets($metricLimit = 10)
    {
        $timezone   = new DateTimeZone($this->timezone);
        $dateRanges = $this->dateRanges();
        $offset     = $this->dateOffset();
        $timeUnit   = $this->timeUnit();
        $datasets   = [];
        
        if( $this->metric ){
            //
            //  Metric
            //

            //
            //  Get top $metricLimit metric list
            //
            $firstDate = clone $dateRanges[0]['start'];
            $lastDate  = clone $dateRanges[count($dateRanges)-1]['end'];
            $query     = DB::table($this->module)
                            ->select([
                                DB::raw('COUNT(*) AS total'), 
                                $this->metric
                            ])
                            ->leftJoin('phone_numbers', 'phone_numbers.id', 'calls.phone_number_id')
                            ->leftJoin('phone_number_pools', 'phone_number_pools.id', 'calls.phone_number_pool_id')
                            ->where(DB::raw("CONVERT_TZ(calls.created_at,'UTC','" . $timezone->getName() . "')"), '>=', $firstDate->format('Y-m-d H:i:s.u'))
                            ->where(DB::raw("CONVERT_TZ(calls.created_at,'UTC','" . $timezone->getName() . "')"), '<=', $lastDate->format('Y-m-d H:i:s.u'))
                            ->where('calls.company_id', $this->company_id)
                            ->groupBy($this->metric);

            $results = $this->applyConditions($query, $this->conditions)
                            ->orderBy('total', $this->metric_order)
                            ->orderBy($this->metric, 'ASC')
                            ->limit($metricLimit)
                            ->get(); 


            //  Pre-populate datasets with 0s
            $metricKey = explode('.', $this->metric);
            $metricKey = end($metricKey);

            $metricValues = array_column($results->toArray(), $metricKey);
            foreach( $metricValues as $mIdx => $metricValue ){
                $data = [];
                foreach( $dateRanges as $dIdx => $dateRange ){
                    $data[] = [
                        'label'  => $metricValue,
                        'x'      => null,
                        'y'      => 0,
                    ];
                }
                $datasets[$metricValue] = [
                    'label' => $metricValue,
                    'data'  => $data
                ];
            }

            if( count($metricValues) ){
                foreach( $dateRanges as $idx => $dateRange ){
                    $data  = [];
                    $query = DB::table($this->module)
                            ->select([
                                    DB::raw('COUNT(*) AS total'), 
                                    $this->metric
                                ])
                                ->leftJoin('phone_numbers', 'phone_numbers.id', 'calls.phone_number_id')
                                ->leftJoin('phone_number_pools', 'phone_number_pools.id', 'calls.phone_number_pool_id')
                                ->where(DB::raw("CONVERT_TZ(calls.created_at,'UTC','" . $timezone->getName() . "')"), '>=', $dateRange['start']->format('Y-m-d H:i:s.u'))
                                ->where(DB::raw("CONVERT_TZ(calls.created_at,'UTC','" . $timezone->getName() . "')"), '<=', $dateRange['end']->format('Y-m-d H:i:s.u'))
                                ->where('calls.company_id', $this->company_id)
                                ->groupBy($this->metric);

                    $results = $this->applyConditions($query, $this->conditions)
                                    ->orderBy('total', $this->metric_order)
                                    ->orderBy($this->metric, 'ASC')
                                    ->limit($metricLimit)
                                    ->get(); 

                    foreach( $results as $result ){
                        if( isset($datasets[$result->$metricKey]) ){
                            $datasets[$result->$metricKey]['data'][$idx]['y'] = $result->total;
                        }
                    }
                }
            }

            $datasets = array_values($datasets);
        }else{
            //
            //  No metric
            //
            foreach( $dateRanges as $dateRange ){
                $data  = [];
                $dbDateFormat = $timeUnit === 'hour' ? '%H' : $dbDateFormat = '%Y-%m-%d';
                
                $query = DB::table($this->module)
                            ->select([
                                DB::raw('COUNT(*) AS total'), 
                                DB::raw("DATE_FORMAT(CONVERT_TZ(calls.created_at,'UTC','" . $timezone->getName() . "'),'" . $dbDateFormat . "') AS create_date") 
                            ])
                            ->where(DB::raw("CONVERT_TZ(calls.created_at,'UTC','" . $timezone->getName() . "')"), '>=', $dateRange['start']->format('Y-m-d H:i:s.u'))
                            ->where(DB::raw("CONVERT_TZ(calls.created_at,'UTC','" . $timezone->getName() . "')"), '<=', $dateRange['end']->format('Y-m-d H:i:s.u'))
                            ->where('calls.company_id', $this->company_id)
                            ->groupBy('create_date');

                $results = $this->applyConditions($query, $this->conditions)
                                ->orderBy('create_date', 'ASC')
                                ->get(); 

                $mappedData = [];
                if( $timeUnit === 'hour' ){ 
                    //
                    // Same day
                    //
                    for( $i = 0; $i < 24; $i++){
                        $mappedData[str_pad($i,2,"0",STR_PAD_LEFT)] = 0;
                    }
                    foreach( $results as $result ){
                        $mappedData[$result->create_date] = $result->total;
                    }
                    foreach( $mappedData as $hour => $count ){
                        $date = new DateTime($dateRange['start']->format('Y-m-d') . ' ' . $hour . ':00:00', $timezone);
                        $data[] = [
                            'label' => $date->format('M j, Y - g:ia'), // Show day and time
                            'x'     => null,
                            'y'     => $count
                        ];
                    }
                    $datasets[] = [
                        'label' => $dateRange['start']->format('M j, Y'), // Show day
                        'data'  => $data
                    ];
                }else{ 
                    //
                    //  Date range
                    //
                    
                    //  Add first date in range
                    $startDate  = clone $dateRange['start'];
                    $endDate    = clone $dateRange['end'];
                    while( $startDate->format('U') <= $endDate->format('U') ){
                        $mappedData[$startDate->format('Y-m-d')] = 0;
                        $startDate->modify('+1 day');
                    }

                    //  Populate with result data
                    foreach( $results as $result )
                        $mappedData[$result->create_date] = $result->total;

                    //  Populate data
                    foreach( $mappedData as $date => $count ){
                        $date = new DateTime($date, $timezone);
                        $data[] = [
                            'label' => $date->format('l, M j, Y'),
                            'x'     => null,
                            'y'     => $count
                        ];
                    }
                    
                    //  Add to dataset
                    $datasets[] = [
                        'label' => $dateRange['start']->format('M j, Y') . ' - ' . $dateRange['end']->format('M j, Y'),
                        'data'  => $data
                    ];
                }                       
            } 
        }

        return $datasets;
    }

    /**
     * Return a stream of exported data
     * 
     */
    public function export($toFile = false)
    {
        $fileName    = preg_replace('/[^0-9A-z]+/', '-', $this->name) . '.xlsx';
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
                    ->setCreator(env('APP_NAME'))
                    ->setLastModifiedBy('System')
                    ->setTitle($this->name)
                    ->setSubject($this->name);
                    
        $moduleLabel = $this->moduleLabel();
        $chartLabels = $this->chartLabels();
        $datasets    = $this->datasets();

        if( $this->metric ){
            //  Export counts with metrics
            $metricLabel = $this->metricLabel();

            $sheet = $spreadsheet->getActiveSheet();
            $row   = 0;
            foreach( $chartLabels as $idx => $datasetName ){
                ++$row;
                $sheet->setCellValue('A' . $row, $datasetName);
                $sheet->getStyle("A$row:B$row")->getFont()->setBold(true);
                $sheet->mergeCells("A$row:B$row");

                ++$row;
                $sheet->setCellValue('A' . $row, 'Metric: ' . $metricLabel);
                $sheet->setCellValue('B' . $row, 'Total ' . $moduleLabel);
                
                foreach( $datasets as $dataset ){
                    ++$row;
                    $sheet->setCellValue('A' . $row, $dataset['label']);
                    $sheet->setCellValue('B' . $row, $dataset['data'][$idx]['y']); 
                }
            }
            $sheet->getColumnDimension('A')->setAutoSize(true);
            $sheet->getColumnDimension('B')->setAutoSize(true);

        }else{
            //  Export counts without metrics
            $sheet = $spreadsheet->getActiveSheet();
            $row   = 0;

            foreach( $datasets as $idx => $dataset ){
                ++$row;
                $sheet->setCellValue('A' . $row, $dataset['label']);
                $sheet->getStyle("A$row:B$row")->getFont()->setBold(true);
                $sheet->mergeCells("A$row:B$row");

                ++$row;

                $sheet->setCellValue('A' . $row, 'Total ' . $moduleLabel);
                $total = 0;
                foreach( $dataset['data'] as $d ){
                    $total += ($d['y'] ?: 0);
                }
                $sheet->setCellValue('B' . $row, $total);
            } 
            //  Auto-resize columns
            $sheet->getColumnDimension('A')->setAutoSize(true);
            $sheet->getColumnDimension('B')->setAutoSize(true);
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="'.$fileName.'"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        if( $toFile ){
            $path = storage_path() . '/' . date('U') . '-' . $fileName;
            $writer->save($path);
            return $path;
        }

        $writer->save('php://output');
    }

    /**
     * Determine if a metric exists for a module
     * 
     */
    static public function metricExists($module, $metric)
    {
        return isset(self::$metrics[$module]) && in_array($metric, array_keys(self::$metrics[$module]));
    }
}
