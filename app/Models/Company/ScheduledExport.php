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
        'next_run_at',
        'delivery_method',
        'delivery_email_addresses',
        'locked_at'
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

    public static function nextRunAt(int $dayOfWeek, int $hourOfDay, string $timezone)
    {
        $now              = now()->setTimeZone($timezone);
        $currentDayOfWeek = $now->format('N');
        $currentHourOfDay = intval($now->format('G'));
        $nextRunAt        = (clone $now)->startOfDay(); 
        $addDays          = 0;
       
        if( $currentDayOfWeek == $dayOfWeek ){
            //  Current day
            if( $currentHourOfDay > $hourOfDay ){
                //  Too late in day
                $addDays = 7;
            }
        }elseif( $currentDayOfWeek > $dayOfWeek){
            //  Past day
            $addDays = (7 - $currentDayOfWeek) + $dayOfWeek;
        }else{
            //  Upcoming day
            $addDays = $currentDayOfWeek - $dayOfWeek;
        }

        $nextRunAt->addDays($addDays);
        $nextRunAt->addHours($hourOfDay);
        
        return $nextRunAt;
    }
}
