<?php

namespace App\Models\Company;

use Illuminate\Database\Eloquent\Model;
use League\Flysystem\Adapter\Local;
use League\Flysystem\Filesystem;
use Cache\Adapter\Filesystem\FilesystemCachePool;
use Cache\Bridge\SimpleCache\SimpleCacheBridge;
use App\Models\Company\ReportAutomation;
use Spreadsheet;
use Xlsx;
use Worksheet;
use SpreadsheetSettings;
use DB;
use DateTime;
use DateTimeZone;

class Report extends Model
{
    protected $fillable = [
        'company_id',
        'user_id',
        'name',
        'module',
        'metric',
        'conditions',
        'order',
        'date_unit',
        'date_offsets',
        'date_ranges',
        'export_separate_tabs',
        'is_system_report'
    ];

    protected $appends = [
        'link',
        'kind'
    ];

    protected $casts = [
        'export_separate_tabs' => 'int',
    ];

    protected $timezone;

    protected $resultSets = [];

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

    static protected $operators = [
        'equals',
        'not_equals',
        'like',
        'not_like',
        'in',
        'not_in',
        'empty',
        'not_empty'
    ];

    static protected $conditionFields = [
        'calls' => [
            'fields' => [
                'calls.source',
                'calls.medium',
                'calls.campaign',
                'calls.content',
                'calls.category',
                'calls.sub_category',
                'calls.caller_city',
                'calls.caller_state',
                'calls.caller_zip',
                'phone_numbers.name',
            ],
            'headers' => [
                'source'            => 'Source',
                'medium'            => 'Medium',
                'campaign'          => 'Campaign',
                'content'           => 'Content',
                'category'          => 'Category',
                'sub_category'      => 'Sub-Category',
                'caller_city'       => 'Caller City',
                'caller_state'      => 'Caller State',
                'caller_zip'        => 'Caller Zip',
                'phone_number_name' => 'Dialed Phone Number'
            ]
        ]
    ];

    static protected $exposedFields = [
        'calls' => [
            'fields' => [
                'calls.id',
                'calls.company_id',
                'companies.name',
                'calls.toll_free',
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
                'toll_free'                 => 'Toll-Free',
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
        return json_decode($dateOffsets) ?: [];
    }

    public function getDateRangesAttribute($dateRanges)
    {
        return json_decode($dateRanges) ?: [];
    }

    public function getConditionsAttribute($conditions)
    {
        return json_decode($conditions) ?: [];
    }

    public function getAutomationsAttribute()
    {
        return ReportAutomation::where('report_id', $this->id)->get();
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
            'type'         => $this->hasMetric() ? self::CHART_TYPE_BAR : self::CHART_TYPE_LINE,
            'step_size'    => 10,
        ];
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

        if( $this->hasMetric() ){
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
                            ->orderBy('id', 'ASC')
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
                return 'Time';
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

    public function allTimeLabels(DateTimeZone $timezone)
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
    public function datasets(DateTimeZone $timezone)
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
                        ->leftJoin('phone_numbers', 'phone_numbers.id', 'calls.phone_number_id')
                        ->leftJoin('phone_number_pools', 'phone_number_pools.id', 'calls.phone_number_pool_id')
                        ->where(DB::raw("CONVERT_TZ(calls.created_at,'UTC','" . $timezone->getName() . "')"), '>=', $earliestDateRange['start']->format('Y-m-d H:i:s.u'))
                        ->where(DB::raw("CONVERT_TZ(calls.created_at,'UTC','" . $timezone->getName() . "')"), '<=', $latestDateRange['end']->format('Y-m-d H:i:s.u'))
                        ->where('calls.company_id', $this->company_id);
            
            $query   = $this->applyConditions($query);
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

                $query   = $this->applyConditions($query);
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

    public function dayRangeDatasets($days, DateTimeZone $timezone)
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

                $query = DB::table('calls')
                            ->select([DB::raw('COUNT(*) AS total'), DB::raw("DATE_FORMAT(CONVERT_TZ(calls.created_at,'UTC','" . $timezone->getName() . "'),'%Y-%m-%d') AS create_date") ])
                            ->where(DB::raw("CONVERT_TZ(calls.created_at,'UTC','" . $timezone->getName() . "')"), '>=', $dateRange['start']->format('Y-m-d H:i:s.u'))
                            ->where(DB::raw("CONVERT_TZ(calls.created_at,'UTC','" . $timezone->getName() . "')"), '<=', $dateRange['end']->format('Y-m-d H:i:s.u'))
                            ->where('calls.company_id', $this->company_id);

                $query   = $this->applyConditions($query);
                $results = $query->groupBy('create_date')
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

    public function dayDatasets(DateTimeZone $timezone)
    {
        $datasets   = [];

        if( $this->module == 'calls' ){
            $dateRanges = $this->dateRanges($timezone);

            foreach( $dateRanges as $idx => $dateRange ){
                $query = DB::table('calls')
                            ->select([DB::raw('COUNT(*) AS total'), DB::raw("DATE_FORMAT(CONVERT_TZ(calls.created_at,'UTC','" . $timezone->getName() . "'),'%H') AS create_date") ])
                            ->where(DB::raw("CONVERT_TZ(calls.created_at,'UTC','" . $timezone->getName() . "')"), '>=', $dateRange['start']->format('Y-m-d H:i:s.u'))
                            ->where(DB::raw("CONVERT_TZ(calls.created_at,'UTC','" . $timezone->getName() . "')"), '<=', $dateRange['end']->format('Y-m-d H:i:s.u'))
                            ->where('calls.company_id', $this->company_id);

                $query   = $this->applyConditions($query);
                $results =  $query->groupBy('create_date')
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

    public function yearDatasets(DateTimeZone $timezone)
    {
        $datasets = [];

        if( $this->module == 'calls' ){
            $dateRanges = $this->dateRanges($timezone);

            foreach( $dateRanges as $idx => $dateRange ){
                $query = DB::table('calls')
                            ->select([DB::raw('COUNT(*) AS total'), DB::raw("DATE_FORMAT(CONVERT_TZ(calls.created_at,'UTC','" . $timezone->getName() . "'),'%Y-%m') AS create_date") ])
                            ->where(DB::raw("CONVERT_TZ(calls.created_at,'UTC','" . $timezone->getName() . "')"), '>=', $dateRange['start']->format('Y-m-d H:i:s.u'))
                            ->where(DB::raw("CONVERT_TZ(calls.created_at,'UTC','" . $timezone->getName() . "')"), '<=', $dateRange['end']->format('Y-m-d H:i:s.u'))
                            ->where('calls.company_id', $this->company_id);

                $query   = $this->applyConditions($query);
                $results = $query->groupBy('create_date')
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

    public function allTimeDatasets(DateTimeZone $timezone)
    {
        $datasets      = [];
        $allTimeCounts = [];
        $labelData     = $this->allTimeLabels($timezone);
        $dateRanges    = $this->dateRanges($timezone);

        $dateFormat = str_replace(['M','Y','y'], ['%b','%Y','%y'], $labelData['date_format']);

        if( $this->module === 'calls' ){
            $query = DB::table('calls')
                        ->select([DB::raw('COUNT(*) AS total'), DB::raw("DATE_FORMAT(CONVERT_TZ(calls.created_at,'UTC','" . $timezone->getName() . "'),'" . $dateFormat . "') AS create_date") ])
                        ->where(DB::raw("CONVERT_TZ(calls.created_at,'UTC','" . $timezone->getName() . "')"), '>=', $dateRanges[0]['start']->format('Y-m-d H:i:s.u'))
                        ->where(DB::raw("CONVERT_TZ(calls.created_at,'UTC','" . $timezone->getName() . "')"), '<=', $dateRanges[0]['end']->format('Y-m-d H:i:s.u'))
                        ->where('calls.company_id', $this->company_id);

            $query   = $this->applyConditions($query);
            $results = $query->groupBy('create_date')
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

    public function recordCount(DateTimeZone $timezone)
    {
        $dateRanges = $this->dateRanges($timezone);

        $query = DB::table($this->module)
                    ->select($this->queryFields())
                    ->leftJoin('phone_numbers', 'phone_numbers.id', 'calls.phone_number_id')
                    ->leftJoin('phone_number_pools', 'phone_number_pools.id', 'calls.phone_number_pool_id')
                    ->leftJoin('companies', 'companies.id', 'calls.company_id')
                    ->where('calls.company_id', $this->company_id);

        $query->where(function($query) use($dateRanges, $timezone){
            foreach( $dateRanges as $idx => $dateRange ){ 
                $where = function($q)use($dateRange, $timezone){
                    $q->whereBetween(DB::raw("CONVERT_TZ(calls.created_at,'UTC','" . $timezone->getName() . "')"), [
                        $dateRange['start']->format('Y-m-d H:i:s.u'),
                        $dateRange['end']->format('Y-m-d H:i:s.u')
                    ]);
                };

                if( $idx === 0 ){
                    $query->where($where);
                }else{
                    $query->orWhere($where);
                }
            }
        });
       
        $query = $this->applyConditions($query);

        return $query->count();
    }
    /**
     * Run a report, returning results
     * 
     */
    public function run(DateTimeZone $timezone, int $limit = 0, int $start = 0)
    {
        $dateRanges = $this->dateRanges($timezone);
        $results    = [];

        $query = DB::table($this->module)
                    ->select($this->queryFields())
                    ->leftJoin('phone_numbers', 'phone_numbers.id', 'calls.phone_number_id')
                    ->leftJoin('phone_number_pools', 'phone_number_pools.id', 'calls.phone_number_pool_id')
                    ->leftJoin('companies', 'companies.id', 'calls.company_id')
                    ->where('calls.company_id', $this->company_id);

        $query->where(function($query) use($dateRanges, $timezone){
            foreach( $dateRanges as $idx => $dateRange ){ 
                $where = function($q)use($dateRange, $timezone){
                    $q->whereBetween(DB::raw("CONVERT_TZ(calls.created_at,'UTC','" . $timezone->getName() . "')"), [
                        $dateRange['start']->format('Y-m-d H:i:s.u'),
                        $dateRange['end']->format('Y-m-d H:i:s.u')
                    ]);
                };

                if( $idx === 0 ){
                    $query->where($where);
                }else{
                    $query->orWhere($where);
                }
            }
        });
       
        $query       = $this->applyConditions($query);
        $resultCount = $query->count();

        $query->offset($start)
              ->limit($limit);

        $records = $query->orderBy('id', 'asc')
                         ->get();

        return [
            'results'              => $records,
            'result_count'         => $resultCount
        ];
    }

    public function runAll(DateTimeZone $timezone)
    {
        $dateRanges = $this->dateRanges($timezone);
        $results    = [];

        foreach( $dateRanges as $idx => $dateRange ){ 
            $query = DB::table($this->module)
                        ->select($this->queryFields())
                        ->leftJoin('phone_numbers', 'phone_numbers.id', 'calls.phone_number_id')
                        ->leftJoin('phone_number_pools', 'phone_number_pools.id', 'calls.phone_number_pool_id')
                        ->leftJoin('companies', 'companies.id', 'calls.company_id')
                        ->where('calls.company_id', $this->company_id)
                        ->whereBetween(DB::raw("CONVERT_TZ(calls.created_at,'UTC','" . $timezone->getName() . "')"), [
                            $dateRange['start']->format('Y-m-d H:i:s.u'),
                            $dateRange['end']->format('Y-m-d H:i:s.u')
                        ]);

            $query   = $this->applyConditions($query);
            $records = $query->orderBy('id', 'asc')
                             ->get();

            $results[] = [
                'results'    => $records,
                'date_range' => $dateRange
            ];
        }

        return $results;
    }

    /**
     * Apply a report's conditions to a query
     * 
     */
    protected function applyConditions($query)
    {
        foreach( $this->conditions as $condition ){
            if( $condition->operator === 'empty' ){
                $query->where(function($q){
                    $q->whereNull($condition->field)
                      ->orWhere($condition->field, '=', '');
                });
            }

            if( $condition->operator === 'not_empty' ){
                $query->where(function($q){
                    $q->whereNotNull($condition->field)
                      ->where($condition->field, '!=', '');
                });
            }

            if( $condition->operator === 'equals' ){
                $query->where($condition->field, '=', $condition->value);
            }
            
            if( $condition->operator === 'not_equals' ){
                $query->where($condition->field, '!=', $condition->value);
            }

            if( $condition->operator === 'like' ){
                $query->where($condition->field, 'like', '%' . $condition->value . '%' );
            }

            if( $condition->operator === 'not_like' ){
                $query->where($condition->field, 'not like', '%' . $condition->value . '%' );
            }

            if( $condition->operator === 'in' ){
                $query->whereIn($condition->field, explode('|', $condition->value));
            }

            if( $condition->operator === 'not_in' ){
                $query->whereNotIn($condition->field, explode('|', $condition->value));
            }
        }

        return $query;
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
     * Build query field list based on the module
     * 
     */
    public function queryFields()
    {
        $exposed = self::$exposedFields[$this->module];
        $fields  = $exposed['fields'];
        $aliases = $exposed['aliases'];

        $fields  = array_map(function($field) use($aliases){
            if( !empty($aliases[$field]) )
                return $field . ' AS ' . $aliases[$field];
            return $field;
        }, $fields);

        return $fields;
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
     * Determine if an exposed field exists for a module
     * 
     */
    static public function exposedFieldExists($module, $field)
    {
        return isset(self::$exposedFields[$module]) && in_array($field, self::$exposedFields[$module]['fields']);
    }

    static public function conditionFieldExists($module, $field)
    {
        return isset(self::$conditionFields[$module]) && in_array($field, self::$conditionFields[$module]['fields']);
    }

    static public function operators()
    {
        return self::$operators;
    }

    static public function headers($module)
    {
        return self::$exposedFields[$module]['headers'];
    }
}
