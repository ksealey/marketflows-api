<?php

namespace App\Models\Company;

use Illuminate\Database\Eloquent\Model;
use DateTime;
use DateTimeZone;

class ReportAutomation extends Model
{
    protected $fillable = [
        'report_id',
        'type',
        'email_addresses',
        'day_of_week',
        'time',
        'run_at'
    ];

    public $casts = [
        'email_addresses' => 'array'
    ];

    protected $hidden = [
        'report_id',
        'run_at',
        'created_at',
        'updated_at'
    ];

    /**
     * Determine the time a report should be ran
     * 
     */
    public static function runAt(int $dayOfWeek, string $time, DateTimeZone $timezone)
    {
        $today = intval((new DateTime())->format('N'));
        $runAt = new DateTime();

        switch($dayOfWeek){
            case 1:
                $day = 'Monday';
                break;

            case 2:
                $day = 'Tuesday';
                break;

            case 3:
                $day = 'Wednesday';
                break;

            case 4:
                $day = 'Thursday';
                break;

            case 5:
                $day = 'Friday';
                break;

            case 6:
                $day = 'Saturday';
                break;

            case 7:
                $day = 'Sunday';
                break;

            default: 
                break;
        }

        

        $runDateTime = new DateTime($runAt->format('Y-m-d') . ' ' . $time, $timezone);
        $runDateTime->setTimeZone(new DateTimeZone('UTC'));

        if( intval($dayOfWeek) !== $today || date('U') > $runDateTime->format('U') ){
            $runAt->modify('next ' . $day);
        }

        $runTime = new DateTime($runAt->format('Y-m-d') . ' ' . $time, $timezone);
        $runTime->setTimeZone(new DateTimeZone('UTC'));

        return $runTime->format('Y-m-d H:i:s');
    }
}
