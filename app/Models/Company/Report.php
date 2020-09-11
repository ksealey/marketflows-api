<?php

namespace App\Models\Company;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Company\ReportAutomation;
use League\Flysystem\Adapter\Local;
use App\Traits\AppliesConditions;
use App\Traits\Helpers\HandlesDateFilters;
use App\Models\User;
use \PhpOffice\PhpSpreadsheet\Cell\DataType as ExcelDaTaype;
use Spreadsheet;
use Xlsx;
use Worksheet;
use SpreadsheetSettings;
use DB;
use DateTime;
use DateTimeZone;

class Report extends Model
{
    use AppliesConditions, HandlesDateFilters, SoftDeletes;

    public $results;
    public $startDate;
    public $endDate;
    public $writePath;

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
    ];

    protected $hidden = [
        'deleted_by',
        'deleted_at'
    ];

    protected $appends = [
        'link',
        'kind'
    ];

    static public $fieldAliases = [
        'calls' => [
            'calls.type'                => 'call_type',
            'calls.category'            => 'call_category',
            'calls.sub_category'        => 'call_sub_category',
            'calls.source'              => 'call_source',
            'calls.medium'              => 'call_medium',
            'calls.content'             => 'call_content',
            'calls.campaign'            => 'call_campaign',
            'calls.recording_enabled'   => 'call_recording_enabled',
            'calls.forwarded_to'        => 'call_forwarded_to',
            'calls.duration'            => 'call_duration',
            'calls.first_call'          => 'call_first_call',
            'contacts.first_name'       => 'contact_first_name',
            'contacts.last_name'        => 'contact_last_name',
            'contacts.phone'            => 'contact_phone',
            'contacts.city'             => 'contact_city',
            'contacts.state'            => 'contact_state',
        ]
    ];

    static public $fieldLabels = [
        'calls' => [
            'call_type'                => 'Type',
            'call_category'            => 'Category',
            'call_sub_category'        => 'Sub-Category',
            'call_source'              => 'Source',
            'call_medium'              => 'Medium',
            'call_content'             => 'Content',
            'call_campaign'            => 'Campaign',
            'call_recording_enabled'   => 'Recording Enabled',
            'call_forwarded_to'        => 'Forwarded To Phone Number',
            'call_duration'            => 'Call Duration',
            'call_first_call'          => 'First Time Caller',
            'contact_first_name'       => 'Caller First Name',
            'contact_last_name'        => 'Caller Last Name',
            'contact_phone'            => 'Caller Phone Number',
            'contact_city'             => 'Caller City',
            'contact_state'            => 'Caller State',
        ]
    ];

    static public function fieldColumn($module, $field)
    {
        foreach( Report::$fields[$module] as $column => $alias ){
            if( $field === $alias ){
                return $column;
            }
        }

        return null;
    }

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
    public function getUserAttribute()
    {
        return User::find($this->created_by);
    }

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

    public function run()
    {
        $query = DB::table($this->module)
                   ->where($this->module . '.company_id', $this->company_id);

        if( $this->type === 'count' ){
            $query->select([
                DB::raw('COUNT(*) AS count'),
                DB::raw($this->group_by . ' AS group_by')
            ])
            ->groupBy('group_by');
        }elseif( $this->type == 'timeframe' ){
            $aliases  = Report::fieldAliases($this->module);
            $selects  = [];
            foreach( $aliases as $key => $alias ){
                $selects[] = $key . ' AS ' . $alias;
            }
            $query->select($selects);
        }

        if( $this->module === 'calls' ){
            //  Add joining tables  
            $query->leftJoin('contacts', 'contacts.id', '=', 'calls.contact_id');
            $query->leftJoin('phone_numbers', 'phone_numbers.id', '=', 'calls.phone_number_id');
        }

        //  Add date ranges
        $timezone = $this->user->timezone;
        $dateType = $this->date_type;

        if( $dateType === 'ALL_TIME' ){
            $dates = $this->getAllTimeDates($this->company->created_at, $timezone);
        }elseif($dateType === 'LAST_N_DAYS' ){
            $dates = $this->getLastNDaysDates($this->last_n_days, $timezone);
        }else{
            $dates = $this->getDateFilterDates($dateType, $timezone, $this->start_date, $this->end_date);
        }

        list($startDate, $endDate) = $dates;

        $this->startDate = $startDate;
        $this->endDate   = $endDate;
        
        $query->where($this->module . ".created_at", '>=', $startDate)
              ->where($this->module . ".created_at", '<=', $endDate);

        //  Add conditions
        if( $this->conditions )
            $query = $this->applyConditions($query, $this->conditions);

        $this->results = $query->get();

        return $this->results;
    }

    public function export($toFile = false)
    {
        $fileName    = preg_replace('/[^0-9A-z]+/', '-', $this->name) . '.xlsx';
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
                    ->setCreator(config('app.name'))
                    ->setLastModifiedBy('System')
                    ->setTitle($this->name)
                    ->setSubject($this->name);
                    
        $results = $this->results ?: $this->run();
        $sheet   = $spreadsheet->getActiveSheet();
        if( $this->type === 'count' ){
            //  Bold title
            $sheet->setCellValue('A1', $this->name . ' (' . $this->startDate->format('M j, Y') . '-' . $this->endDate->format('M j, Y')  . ')');
            $sheet->getStyle("A1:B1")->getFont()->setBold(true);
            $sheet->mergeCells("A1:B1");

            //  Bold header cells
            $header = $this->fieldLabel($this->module, Report::fieldAlias($this->module, $this->group_by));
            $sheet->setCellValue('A3', $header);
            $sheet->setCellValue('B3', 'Total');
            $sheet->getStyle("A3:B3")->getFont()->setBold(true);

            //  Values
            $row = 4;
            foreach( $results as $idx => $result ){
                $sheet->setCellValue('A' . $row, $result->group_by);
                $sheet->setCellValue('B' . $row, $result->count); 

                $row++;
            }

            //  Space out cells
            $sheet->getColumnDimension('A')->setAutoSize(true);
            $sheet->getColumnDimension('B')->setAutoSize(true);

        }elseif( $this->type === 'timeframe' ){
            //  Write bold headers
            $col = 'A';
            foreach( Report::$fieldNames[$this->module] as $name ){
                $sheet->setCellValue($col . "1", $name);
                $col++;
            }
            $sheet->getStyle("A1:" . $col . "1")->getFont()->setBold(true);

            //  Write data
            $row = 2;
            foreach( $results as $result ){
                $result = (array)$result;
                $col    = 'A';
                foreach( $result as $value){
                    $sheet->setCellValueExplicit($col . $row, $value, ExcelDaTaype::TYPE_STRING);
                    $col++;    
                }
                $row++;
            }
        }


        $writer    = new Xlsx($spreadsheet);
        if( $toFile ){
            $this->writePath = storage_path(str_random(40) . '.xlsx');
            
            $writer->save($this->writePath );
        }else{
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="'.$fileName.'"');
            header('Cache-Control: max-age=0');

            $writer->save('php://output');
        }

        return $this;
    }

    static public function fieldLabel($module, $fieldAlias)
    {
        return Report::$fieldLabels[$module][$fieldAlias];
    }

    static public function fieldAliases($module)
    {
        return Report::$fieldAliases[$module];
    }

    static public function fieldAlias($module, $column)
    {
        return Report::$fieldAliases[$module][$column];
    }
}
