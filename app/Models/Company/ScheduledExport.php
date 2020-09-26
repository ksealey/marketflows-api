<?php

namespace App\Models\Company;

use Illuminate\Database\Eloquent\Model;
use DB;

class ScheduledExport extends Model
{
    protected $fillable = [
        'company_id',
        'report_id',
        'day_of_week',
        'hour_of_day',
        'timezone',
        'delivery_method',
        'delivery_email_addresses',
        'last_export_at'
    ];

    protected $appends = [
        'url',
        'kind'
    ];

    static public function exports() : array
    {
        return [
            'id'                          => 'Id',
            'company_id'                  => 'Company Id',
            'report_name'                 => 'Exported Report',
            'day_of_week_day'             => 'Day of Week',
            'hour_of_day_time'            => 'Time of Day',
            'timezone'                    => 'Time Zone',
            'delivery_method'             => 'Delivery Method',
            'delivery_email_address_list' => 'Delivery Email Addresses',
            'last_export_at'              => 'Last Export',
            'created_at_local'            => 'Created'
        ];
    }

    static public function exportFileName($user, array $input) : string
    {
        return 'Scheduled Exports - ' . $input['company_name'];
    }

    static public function exportQuery($user, array $input)
    {
        return ScheduledExport::select([
                    'scheduled_exports.*',
                    'reports.name AS report_name',
                    'delivery_email_addresses AS delivery_email_address_list',
                    DB::raw("CASE WHEN day_of_week = 0
                                    THEN 'Sunday'
                                WHEN day_of_week = 1
                                    THEN 'Monday'
                                WHEN day_of_week = 2
                                    THEN 'Tuesday'
                                WHEN day_of_week = 3
                                    THEN 'Wednesday'
                                WHEN day_of_week = 4
                                    THEN 'Thursday'
                                WHEN day_of_week = 5
                                    THEN 'Friday'
                                WHEN day_of_week = 6
                                    THEN 'Saturday'
                                ELSE day_of_week
                                END AS day_of_week_day"),
                    DB::raw("CASE WHEN hour_of_day < 12
                                THEN CONCAT(hour_of_day, ':00am')
                            WHEN hour_of_day = 12
                                THEN CONCAT(hour_of_day, ':00pm')
                            ELSE CONCAT(hour_of_day % 12, ':00pm')
                            END AS hour_of_day_time"),
                    DB::raw("DATE_FORMAT(CONVERT_TZ(phone_numbers.created_at, 'UTC','" . $user->timezone . "'), '%b %d, %Y') AS created_at_local")
                ])
                ->where('scheduled_exports.company_id', $input['company_id'])
                ->leftJoin('reports', 'reports.id', 'scheduled_exports.report_id');
    }

    /**
     * Appends
     * 
     */
    public function getUrlAttribute()
    {
        return null;
    }
   
    public function getKindAttribute()
    {
        return 'ScheduledExport';
    }

    public function getDeliveryEmailAddressesAttribute($emailString)
    {
        return explode(',', $emailString);
    }

    public function report()
    {
        return $this->belongsTo('App\Models\Company\Report');
    }
}
