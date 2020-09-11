<?php

namespace App\Models\Company;

use Illuminate\Database\Eloquent\Model;

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
