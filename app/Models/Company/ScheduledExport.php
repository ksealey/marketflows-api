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
        'delivery_method',
        'delivery_email_addresses',
        'last_ran_at',
    ];

    public function getDeliveryEmailAddressesAttribute($emailString)
    {
        return explode(',', $emailString);
    }
}
