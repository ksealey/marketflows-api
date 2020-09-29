<?php

namespace App\Models\Company;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Company\ReportAutomation;
use League\Flysystem\Adapter\Local;
use App\Traits\AppliesConditions;
use App\Traits\Helpers\HandlesDateFilters;
use App\Models\User;
use \PhpOffice\PhpSpreadsheet\Cell\DataType as ExcelDataType;
use App\Services\ReportService;
use Spreadsheet;
use Xlsx;
use Worksheet;
use SpreadsheetSettings;
use DB;
use DateTime;
use DateTimeZone;
use App;
use \Carbon\Carbon;

class Report extends Model
{
    use AppliesConditions, HandlesDateFilters, SoftDeletes;

    public $results;
    public $startDate;
    public $endDate;
    public $writePath;
    public $reportService;

    protected $fillable = [
        'account_id',
        'company_id',
        'created_by',
        'updated_by',
        'deleted_by',
        'name',
        'module',
        'type',
        'group_by',
        'date_type',
        'last_n_days',
        'start_date',
        'end_date',
        'conditions',
        'link',
        'vs_previous_period'
    ];

    protected $hidden = [
        'deleted_by',
        'deleted_at'
    ];

    protected $appends = [
        'link',
        'kind'
    ];

    static public function exports() : array
    {
        return [
            'id'                => 'Id',
            'company_id'        => 'Company Id',
            'name'              => 'Name',
            'created_at_local'  => 'Created'
        ];
    }

    static public function exportFileName($user, array $input) : string
    {
        return 'Reports - ' . $input['company_name'];
    }

    static public function exportQuery($user, array $input)
    {
        return Report::select([
                        'reports.*',
                        DB::raw("DATE_FORMAT(CONVERT_TZ(phone_numbers.created_at, 'UTC','" . $user->timezone . "'), '%b %d, %Y') AS created_at_local")
                    ])
                     ->where('reports.company_id', $input['company_id']);
    }

    public function __destruct()
    {
        if( $this->writePath ){
            unlink($this->writePath);
        }
    }

    public function company()
    {
        return $this->belongsTo('\App\Models\Company');
    }

    /**
     * Attributes
     * 
     */
    public function getUserAttribute($user)
    {
        if( $user ) return $user;

        return User::find($this->created_by);
    }

    public function getLinkAttribute($link)
    {
        if( ! empty($this->link) ) 
            return $this->link;

        if( $link )
            return $link;

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

    public function run($condense = true)
    {   
        $this->reportService = App::make(ReportService::class);

        $timezone = $this->user->timezone;
        $dateType = $this->date_type;
        $datasets = [];

        //
        //  Get dates
        //
        if( $dateType === 'ALL_TIME' ){
            $dates = $this->getAllTimeDates($this->company->created_at, $timezone);
        }elseif($dateType === 'LAST_N_DAYS' ){
            $dates = $this->getLastNDaysDates($this->last_n_days, $timezone);
        }else{
            $dates = $this->getDateFilterDates($dateType, $timezone, $this->start_date, $this->end_date);
        }

        list($startDate, $endDate) = $dates;

        $dateRangeSets   = [];
        $dateRangeSets[] = [$startDate, $endDate];
        if( $this->vs_previous_period ){
            $dateRangeSets[] = $this->getPreviousDateFilterPeriod($startDate, $endDate);
        }
        
        foreach( $dateRangeSets as $dateRangeSet ){
            list($_startDate, $_endDate) = $dateRangeSet;

            //
            //  Set table and initial filters
            //
            $query = DB::table($this->module)
                        ->where($this->module . '.company_id', $this->company_id)
                        ->where(DB::raw("CONVERT_TZ(" . $this->module . ".created_at, 'UTC', '" . $timezone . "')"), '>=', $_startDate->format('Y-m-d H:i:s'))
                        ->where(DB::raw("CONVERT_TZ(" . $this->module . ".created_at, 'UTC', '" . $timezone . "')"), '<=', $_endDate->format('Y-m-d H:i:s'))
                        ->whereNull($this->module . '.deleted_at');

            //
            //  Add selects
            //
            
            if( $this->type === 'count' ){
                list($table, $column) =  explode('.', $this->group_by);

                $groupBy        = $this->group_by;
                $groupByType    = $this->reportService->fieldType($this->module, $this->group_by);
            }elseif( $this->type === 'timeframe' ){
                $dbDateFormat   = $this->getDBDateFormat($_startDate, $_endDate);
                $groupBy        = "(DATE_FORMAT(CONVERT_TZ(" . $this->module . ".created_at, 'UTC', '" . $timezone . "'), '" . $dbDateFormat . "'))";
                $groupByType    = "datetime";
            }
            
            $query->select([
                DB::raw("COUNT(*) AS count"),
                DB::raw($groupBy . " AS group_by"),
                DB::raw("'" . $groupByType . "'". " AS group_by_type")
            ])
            ->groupBy('group_by');

            //
            //  Add conditions
            //
            if( $this->conditions )
                $query = $this->applyConditions($query, $this->conditions);

            //
            //  Add joining tables 
            //
            if( $this->module === 'calls' ){
                $query->leftJoin('contacts', 'contacts.id', '=', 'calls.contact_id');
                $query->leftJoin('phone_numbers', 'phone_numbers.id', '=', 'calls.phone_number_id');
            }
            
            $callData   = $query->get();  
            $data       = [];
            if( $this->type === 'count' ){
                $groupKeys = array_column($callData->toArray(), 'group_by');
                $data      = $this->reportService->barDatasetData($callData, $groupKeys, $condense);
            }elseif( $this->type === 'timeframe' ){
                $data = $this->reportService->lineDatasetData($callData, $_startDate, $_endDate);
            }   
            
            $datasets[] = [
                'label' => $_startDate->format('M j, Y') . ($_startDate->diff($_endDate)->days ? (' - ' . $_endDate->format('M j, Y')) : ''),
                'data'  => $data,
                'total' => $this->reportService->total($callData)
            ];
        }

        $labels = [];
        if( $this->type === 'count' ){
            $labels = $this->reportService->countLabels($callData, $condense);
            $alias  = $this->reportService->fieldAlias($this->module, $this->group_by);
            $title  = $this->reportService->fieldLabel($this->module, $alias); 
        }elseif( $this->type === 'timeframe' ){
            $labels = $this->reportService->timeframeLabels($startDate, $endDate);
            $title  = $this->reportService->moduleLabel($this->module);
        } 

        return [
            'type'     => $this->type,
            'title'    => $title,
            'labels'   => $labels,
            'datasets' => $datasets,
        ];
    }

    public function runDetailed()
    {   
        $this->reportService = App::make(ReportService::class);

        $timezone = $this->user->timezone;
        $dateType = $this->date_type;

        //
        //  Get dates
        //
        if( $dateType === 'ALL_TIME' ){
            $dates = $this->getAllTimeDates($this->company->created_at, $timezone);
        }elseif($dateType === 'LAST_N_DAYS' ){
            $dates = $this->getLastNDaysDates($this->last_n_days, $timezone);
        }else{
            $dates = $this->getDateFilterDates($dateType, $timezone, $this->start_date, $this->end_date);
        }

        list($_startDate, $_endDate) = $dates;

        //
        //  Set table and initial filters
        //
        $aliases = [];
        foreach($this->reportService->fieldAliases($this->module) as $key => $alias){
            $aliases[] = $key . ' AS ' . $alias;
        }
        $query = DB::table($this->module)
                    ->select($aliases)
                    ->where($this->module . '.company_id', $this->company_id)
                    ->where(DB::raw("CONVERT_TZ(" . $this->module . ".created_at, 'UTC', '" . $timezone . "')"), '>=', $_startDate)
                    ->where(DB::raw("CONVERT_TZ(" . $this->module . ".created_at, 'UTC', '" . $timezone . "')"), '<=', $_endDate)
                    ->whereNull($this->module . '.deleted_at');

        //
        //  Add conditions
        //
        if( $this->conditions )
            $query = $this->applyConditions($query, $this->conditions);

        //
        //  Add joining tables 
        //
        if( $this->module === 'calls' ){
            $query->leftJoin('contacts', 'contacts.id', '=', 'calls.contact_id');
            $query->leftJoin('phone_numbers', 'phone_numbers.id', '=', 'calls.phone_number_id');
        }

        return $query->get(); 
    }

    public function export($toFile = false)
    {
        $this->reportService = App::make(ReportService::class);

        $fileName    = preg_replace('/[^0-9A-z]+/', '-', $this->name) . '.xlsx';
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
                    ->setCreator(config('app.name'))
                    ->setLastModifiedBy('System')
                    ->setTitle($this->name)
                    ->setSubject($this->name);
                    
        $sheet   = $spreadsheet->getActiveSheet();

        list($startDate, $endDate) = $this->getReportDates();

        if( $this->type === 'count' ){
            /**
             * --------------
             * M j, Y - M j, Y
             * --------------
             * 
             * -------------------
             * FIELD_LABEL | TOTAL
             * -------------------
             * -           | -
             * -------------------
             * -           | -  
             * -------------------
             */
            $results = $this->run(false);

            //  Bold title
            $sheet->setCellValue('A1', $this->name . ' (' . $startDate->format('M j, Y') . '-' . $endDate->format('M j, Y')  . ')');
            $sheet->getStyle("A1:B1")->getFont()->setBold(true);
            $sheet->mergeCells("A1:B1");

            //  Bold header cells
            $alias  = $this->reportService->fieldAlias($this->module, $this->group_by);
            $header = $this->reportService->fieldLabel($this->module, $alias);
            
            $sheet->setCellValue('A3', $header);
            $sheet->setCellValue('B3', 'Total');
            $sheet->getStyle("A3:B3")->getFont()->setBold(true);

            //  Values
            $row = 4;
            $labels = $results['labels'];
            $totals = array_map(function($dataPiece){
                return $dataPiece['value'];
            }, $results['datasets'][0]['data']);

            foreach( $labels as $idx => $label ){
                $sheet->setCellValue('A' . $row, $label);
                $sheet->setCellValue('B' . $row, $totals[$idx]); 

                $row++;
            }

            //  Space out cells
            $sheet->getColumnDimension('A')->setAutoSize(true);
            $sheet->getColumnDimension('B')->setAutoSize(true);
        }elseif( $this->type === 'timeframe' ){
            /**
             * -------------------
             * HEADERS
             * -------------------
             * -           | -  
             * -------------------
             * -           | -  
             * -------------------
             */
            $user    = $this->user;
            $results = $this->runDetailed();
            $col     = 'A';
            $lastCol = 'A';

            //  Write bold headers 
            foreach( $this->reportService->fieldAliases($this->module) as $alias ){
                $lastCol = $col;
                $name = $this->reportService->fieldLabel($this->module, $alias);
                $sheet->setCellValue($col . "1", $name);
                $col++;
            }
            $sheet->getStyle("A1:" . $lastCol . "1")->getFont()->setBold(true);

            //  Write data
            $row = 2;
            if( count($results) ){
                foreach( $results as $result ){
                    $result = (array)$result;
                    $col    = 'A';
                    foreach( $result as $prop => $value ){
                        if( $format = $this->reportService->dateField($this->module, $prop) ){
                            $value = (new Carbon($value))->setTimeZone($user->timezone)->format($format);
                        }
                        if( $this->reportService->booleanField($this->module, $prop) ){
                            $value = $value ? 'Yes' : 'No';
                        }
                        $sheet->setCellValueExplicit($col . $row, $value, ExcelDataType::TYPE_STRING);
                        $col++;    
                    }
                    $row++;
                }
            }

            //  Resize columns
            $resizeCol = 'A';
            while( $resizeCol !== $col ){
                $sheet->getColumnDimension($resizeCol)->setAutoSize(true);
                $resizeCol++;
            }
        }


        $writer = new Xlsx($spreadsheet);
        if( $toFile ){
            $this->writePath = storage_path(str_random(40) . '.xlsx');
            
            $writer->save($this->writePath);
        }else{
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="'.$fileName.'"');
            header('Cache-Control: max-age=0');

            $writer->save('php://output');
        }

        return $this;
    }

    public function getReportDates()
    {
        $timezone = $this->user->timezone;
        $dateType = $this->date_type;

        if( $dateType === 'ALL_TIME' ){
            return $this->getAllTimeDates($this->company->created_at, $timezone);
        }elseif($dateType === 'LAST_N_DAYS' ){
            return $this->getLastNDaysDates($this->last_n_days, $timezone);
        }else{
            return $this->getDateFilterDates($dateType, $timezone, $this->start_date, $this->end_date);
        }
    }
}
