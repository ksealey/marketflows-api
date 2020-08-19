<?php

namespace App\Models\Company;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Company\ReportAutomation;
use League\Flysystem\Adapter\Local;
use App\Traits\AppliesConditions;
use \App\Traits\PerformsExport;
use App\Traits\Helpers\HandlesDateFilters;
use App\Models\User;
use Spreadsheet;
use Xlsx;
use Worksheet;
use SpreadsheetSettings;
use DB;
use DateTime;
use DateTimeZone;

class Report extends Model
{
    use AppliesConditions, PerformsExport, HandlesDateFilters, SoftDeletes;

    public $results;
    public $startDate;
    public $endDate;

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
            ->groupBy($this->group_by);
        }elseif( $this->type == 'timeframe' ){
            if( $this->module === 'calls' ){
                $query->select([
                    'calls.type',
                    'calls.category',
                    'calls.sub_category',
                    'calls.source',
                    'calls.medium',
                    'calls.content',
                    'calls.campaign',
                    'calls.recording_enabled',
                    'calls.forwarded_to',
                    'calls.duration',
                    'calls.first_call',
                    'contacts.first_name',
                    'contacts.last_name',
                    'contacts.phone',
                    'contacts.city',
                    'contacts.state',
                ]);
            }
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
}
