<?php

namespace App\Models\Company;

use Illuminate\Database\Eloquent\Model;
use App\Models\Company\ReportAutomation;
use League\Flysystem\Adapter\Local;
use League\Flysystem\Filesystem;
use Cache\Adapter\Filesystem\FilesystemCachePool;
use Cache\Bridge\SimpleCache\SimpleCacheBridge;
use App\Traits\AppliesConditions;
use Spreadsheet;
use Xlsx;
use Worksheet;
use SpreadsheetSettings;
use DB;
use DateTime;
use DateTimeZone;

class Report extends Model
{
    use AppliesConditions;

    protected $fillable = [
        'company_id',
        'user_id',
        'name',
        'module',
        'metric',
        'order',
        'date_type',
        'comparisons',
        'conditions',
        'start_date',
        'end_date',
        'export_separate_tabs',
        'is_system_report'
    ];

    protected $appends = [
        'link',
        'kind'
    ];

    protected $casts = [
        'export_separate_tabs' => 'int',
        'comparisons'          => 'array',
        'conditions'           => 'array'
    ];

    protected $timezone;

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
                'calls.forwarded_to',
                'calls.direction',
                'calls.status',
                'calls.duration',
                'calls.recording_enabled',
                'calls.caller_id_enabled',
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
                'caller_first_name'         => 'Caller First Name',
                'caller_last_name'          => 'Caller Last Name',
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
                'caller_id_enabled'         => 'Caller Id Enabled',
                'created_at'                => 'Call Time'
            ]
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

    /*
    public function getConditionsAttribute($conditions)
    {
        return is_string($conditions) ? json_decode($conditions) : $conditions;
    }*/

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
    public function charts(DateTimeZone $timezone)
    {
        //  Get the date unit
        $unit      = $this->timeUnit($timezone);
        $unitLabel = $this->timeUnitLabel($timezone, $unit);
        
        //  Get the chart's type
        $chartType = $this->metric ? 'bar' : 'line';

        //  Get the chart's title
        $chartTitle  = $this->moduleLabel();
        if( $this->metric )
            $chartTitle .= ' by ' . $this->metricLabel();
        if( $this->comparisons )
            $chartTitle .= ' (Comparison)';
    
        //  Get the charts labels
        $chartLabels = $this->chartLabels($timezone);

        //  Get step size
        $stepSize = $this->stepSize($timezone);

        //  Get the datasets
        $datasets = $this->datasets($timezone);
        
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

    public function chartLabels(DateTimeZone $timezone)
    {
        $labels     = [];
        $timeUnit   = $this->timeUnit($timezone);
        $dateOffset = $this->dateOffset($timezone);

        if( $this->metric ){
            //  With metric
            $dateRanges  = $this->dateRanges($timezone);
            foreach( $dateRanges as $dateRange ){
                $label    = $dateRange['start']->format('M, j Y');
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
                    $dateRange  = $this->dateRanges($timezone)[0];
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

    public function stepSize(DateTimeZone $timezone)
    {
        return 10; // TODO: Actually calulate step size
    }

    /**
     * Get the amount of days between the current date range
     * 
     */
    public function dateOffset(DateTimeZone $timezone)
    {
        $offset = 0;
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

    public function timeUnit($timezone)
    {
        $dateOffset = $this->dateOffset($timezone);
        if( ! $dateOffset )
            return 'hour';
        return 'day';
    }

    public function timeUnitLabel($timezone, $unit = null)
    {
        if( $this->metric )
            return '';

        if( ! $unit ) $unit = $this->timeUnit($timezone);
        
        return $unit === 'hour' ? 'Time' : 'Day';
    }

    /**
     * Convert a report's settings into date ranges, ordered from earliest to most recent
     * 
     */
    public function dateRanges(DateTimeZone $timezone)
    {
        $dateSets   = [];
        $dateRanges = [];

        $offset     = $this->dateOffset($timezone);
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
    public function datasets(DateTimeZone $timezone, $metricLimit = 10)
    {
        $dateRanges = $this->dateRanges($timezone);
        $offset     = $this->dateOffset($timezone);
        $timeUnit   = $this->timeUnit($timezone);
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
                            ->orderBy('total', $this->order)
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
                                    ->orderBy('total', $this->order)
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
    public function export(DateTimeZone $timezone)
    {
        
        /*
        $filesystemAdapter = new Local(storage_path());
        $filesystem        = new Filesystem($filesystemAdapter);
        $pool              = new FilesystemCachePool($filesystem);
        $simpleCache       = new SimpleCacheBridge($pool);
        SpreadsheetSettings::setCache($simpleCache);
        */
        $fileName    = preg_replace('/[^0-9A-z]+/', '-', $this->name) . '.xlsx';
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
                    ->setCreator(env('APP_NAME'))
                    ->setLastModifiedBy('System')
                    ->setTitle($this->name)
                    ->setSubject($this->name);
                    
        $moduleLabel = $this->moduleLabel();
        $dataLabels  = $this->dataLabels($timezone);
        $datasets    = $this->datasets($timezone);

        if( $this->metric ){
            //  Export counts with metric
            $metricLabel = $this->metricLabel();

            if( $this->export_separate_tabs ){
                //  With separate tabs
                foreach( $dataLabels as $idx => $tabName ){
                    if( ! $idx ) // Remove initial worksheet
                        $spreadsheet->removeSheetByIndex($idx);

                    $sheet = new Worksheet($spreadsheet, $tabName);
                    $spreadsheet->addSheet($sheet, $idx);

                    //  Set headers
                    $sheet->setCellValue('A1', 'Metric: ' . $metricLabel);
                    $sheet->setCellValue('B1', 'Total ' . $moduleLabel);

                    //  Set data
                    $row = 2;
                    foreach( $datasets as $dataset ){
                        $sheet->setCellValue('A' . $row, $dataset['label']);
                        $sheet->setCellValue('B' . $row, $dataset['data'][$idx]);

                        ++$row;
                    }

                    //  Auto-resize columns
                    $sheet->getColumnDimension('A')->setAutoSize(true);
                    $sheet->getColumnDimension('B')->setAutoSize(true);
                }
            }else{
                //  On same tab
                $sheet = $spreadsheet->getActiveSheet();
                $row   = 0;
                foreach( $dataLabels as $idx => $datasetName ){
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
                        $sheet->setCellValue('B' . $row, $dataset['data'][$idx]); 
                    }
                }
                $sheet->getColumnDimension('A')->setAutoSize(true);
                $sheet->getColumnDimension('B')->setAutoSize(true);
            }
        }else{
            //  Export counts without metrics
            $rangeLabel  = $this->rangeLabel();
            if( $this->export_separate_tabs ){
                //  ... on separate tabs
                foreach( $datasets as $idx => $dataset ){
                    if( ! $idx ) // Remove initial worksheet
                        $spreadsheet->removeSheetByIndex($idx);
                        
                    $sheet = new Worksheet($spreadsheet, $dataset['label']);
                    $spreadsheet->addSheet($sheet, $idx);

                    $sheet->setCellValue('A1', $rangeLabel);
                    $sheet->setCellValue('B1', 'Total ' . $moduleLabel);

                    $row = 2;
                    foreach( $dataLabels as $dlIdx => $dataLabel ){
                        $sheet->setCellValue('A' . $row, $dataLabel);
                        $sheet->setCellValue('B' . $row, $dataset['data'][$dlIdx]);
                        ++$row;
                    }
                    //  Auto-resize columns
                    $sheet->getColumnDimension('A')->setAutoSize(true);
                    $sheet->getColumnDimension('B')->setAutoSize(true);
                } 
            }else{
                //  ... on same tab
                $sheet = $spreadsheet->getActiveSheet();
                $row   = 0;
                foreach( $datasets as $idx => $dataset ){
                    ++$row;
                    $sheet->setCellValue('A' . $row, $dataset['label']);
                    $sheet->getStyle("A$row:B$row")->getFont()->setBold(true);
                    $sheet->mergeCells("A$row:B$row");

                    ++$row;
                    $sheet->setCellValue('A' . $row, $rangeLabel);
                    $sheet->setCellValue('B' . $row, 'Total ' . $moduleLabel);

                    foreach( $dataLabels as $dlIdx => $dataLabel ){
                        ++$row;
                        $sheet->setCellValue('A' . $row, $dataLabel);
                        $sheet->setCellValue('B' . $row, $dataset['data'][$dlIdx]);
                    }
                } 
                //  Auto-resize columns
                $sheet->getColumnDimension('A')->setAutoSize(true);
                $sheet->getColumnDimension('B')->setAutoSize(true);
            }
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="'.$fileName.'"');
        header('Cache-Control: max-age=0');

        $writer   = new Xlsx($spreadsheet);
        $writer->save('php://output');
    }

    /**
     * Labels
     * 
     */
    public function metricLabels(DateTimeZone $timezone)
    {
        //  Use date ranges as bottom labels
        $dateRanges = $this->dateRanges($timezone);
        $offsetDays = $this->dateOffset($timezone);
        $labels     = [];

        if( ! $offsetDays ){ // Same day, return nothing since this will be handled
           
        }

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

    public function nonMetricWithComparisonDatasets(DateTimeZone $timezone)
    {
        $datasets     = [];
        $dateRanges   = $this->dateRanges($timezone);
        $dateFormat   = 'Y-m-d';
        $dbDateFormat = '%Y-%m-%d';

        foreach( $dateRanges as $idx => $dateRange ){
            $query = DB::table('calls')
                        ->select([DB::raw('COUNT(*) AS total'), DB::raw("DATE_FORMAT(CONVERT_TZ(calls.created_at,'UTC','" . $timezone->getName() . "'),'" . $dbDateFormat . "') AS create_date") ])
                        ->where(DB::raw("CONVERT_TZ(calls.created_at,'UTC','" . $timezone->getName() . "')"), '>=', $dateRange['start']->format('Y-m-d H:i:s.u'))
                        ->where(DB::raw("CONVERT_TZ(calls.created_at,'UTC','" . $timezone->getName() . "')"), '<=', $dateRange['end']->format('Y-m-d H:i:s.u'))
                        ->where('calls.company_id', $this->company_id);

            $query   = $this->applyConditions($query, $this->conditions);
            $results =  $query->groupBy('create_date')
                                ->orderBy('total', $this->order)
                                ->get();
                                
            if( ! $offset ){ // Time
                
            }else{ // Days

                if( $this->comparisons ){
                    //  Add to map by date
                    $resultsByDate = [];
                    foreach( $results as $result ){
                        $resultsByDate[$result->create_date] = $result->total;
                    }

                    //  Loop for each day
                    $start       = clone $dateRange['start'];
                    $end         = clone $dateRange['end'];
                    $data        = [];
                    while( $start->format('U') <= $end->format('U') ){
                        $data[]       = [
                            'label' => $start->format('M jS, Y'),
                            'x'     => null,
                            'y'     => $resultsByDate[$start->format('Y-m-d')] ?? 0
                        ];
                        $start->modify('+1 day');
                    }
                    
                    $label = $dateRange['start']->format('M d, Y') . ' - ' . $dateRange['end']->format('M d, Y') ;
                    
                    $datasets[] = [
                        'label'         => $label,
                        'data'          => $data
                    ];
                }else{

                }
            }
        }
    }
    
    /**
     * Get a dataset for a report that has a metric
     * 
     * @return array
     */
    public function metricWithComparisonDatasets(DateTimeZone $timezone)
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
                        ->leftJoin('phone_numbers', 'phone_numbers.id', 'calls.phone_number_id')
                        ->leftJoin('phone_number_pools', 'phone_number_pools.id', 'calls.phone_number_pool_id')
                        ->where(DB::raw("CONVERT_TZ(calls.created_at,'UTC','" . $timezone->getName() . "')"), '>=', $earliestDateRange['start']->format('Y-m-d H:i:s.u'))
                        ->where(DB::raw("CONVERT_TZ(calls.created_at,'UTC','" . $timezone->getName() . "')"), '<=', $latestDateRange['end']->format('Y-m-d H:i:s.u'))
                        ->where('calls.company_id', $this->company_id);
            
            $query   = $this->applyConditions($query, $this->conditions);
            $query->groupBy($this->metric)
                  ->orderBy('total', $this->order);
            $results = $query->get();
       
            $metricList = array_column($results->toArray(), $metricKey);
            $datasets  = [];
            foreach( $metricList as $idx => $metricValue ){
                if( $idx >= 10 )
                    break;

                $datasets[$metricValue] = [];
            }
            
            foreach( $dateRanges as $idx => $dateRange ){
                $query = DB::table('calls')
                            ->select([DB::raw('COUNT(*) AS total'), $this->metric])
                            ->leftJoin('phone_numbers', 'phone_numbers.id', 'calls.phone_number_id')
                            ->leftJoin('phone_number_pools', 'phone_number_pools.id', 'calls.phone_number_pool_id')
                            ->where(DB::raw("CONVERT_TZ(calls.created_at,'UTC','" . $timezone->getName() . "')"), '>=', $dateRange['start']->format('Y-m-d H:i:s.u'))
                            ->where(DB::raw("CONVERT_TZ(calls.created_at,'UTC','" . $timezone->getName() . "')"), '<=', $dateRange['end']->format('Y-m-d H:i:s.u'))
                            ->where('calls.company_id', $this->company_id);

                $query   = $this->applyConditions($query, $this->conditions);
                $results = $query->groupBy($this->metric)
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

    /**
     * Determine if a metric exists for a module
     * 
     */
    static public function metricExists($module, $metric)
    {
        return isset(self::$metrics[$module]) && in_array($metric, array_keys(self::$metrics[$module]));
    }
}
