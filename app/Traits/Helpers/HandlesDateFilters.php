<?php
namespace App\Traits\Helpers;
use App\Models\Company;
use App\Helpers\Formatter;
use DateTime;
use DateTimeZone;
use Exception;

trait HandlesDateFilters
{ 
    public function startDate($dateRangeStr, $timezoneStr)
    {
        $userTZ    = new DateTimeZone($timezoneStr);
        $targetTZ  = new DateTimeZone('UTC');

        $defaultDate = new DateTime('1970-01-01 00:00:00', $userTZ);
        $defaultDate->setTimeZone($targetTZ);

        if( ! $dateRangeStr )
            return $defaultDate;

        $dateRange = json_decode($dateRangeStr);
        if( $dateRange->key === 'CUSTOM' ){
            if( ! isset($dateRange->start_date) )
                return $defaultDate;

            $date = DateTime::createFromFormat('Y-m-d', $dateRange->start_date);
            if( ! $date )
                return $defaultDate;

            $date = new DateTime($dateRange->start_date . ' 00:00:00', $userTZ); 
            $date->setTimeZone($targetTZ);
        
            return $date;
        }

        //  Preset
        //

        //  Use current date as base
        $date = new DateTime(date('Y-m-d') . ' 00:00:00', $userTZ);
        switch($dateRange->key){
            case 'TODAY': 
                break;

            case 'YESTERDAY_TO_DATE':
                $date->modify('-1 day');
            break;

            case 'LAST_7_DAYS':
                $date->modify('-7 days');
            break;

            case 'LAST_30_DAYS':
                $date->modify('-30 days');
            break;

            case 'LAST_4_WEEKS':
                $date->modify('-4 weeks');
            break;

            case 'LAST_3_MONTHS':
                $date->modify('-3 months');
            break;

            case 'WEEK_TO_DATE':
                $date->modify('- ' . (date('N')-1) . ' days');
            break;

            case 'MONTH_TO_DATE':
                $date->modify('- ' . (date('j')-1) . ' days');
            break;

            case 'YEAR_TO_DATE':
                $date->modify('- ' . (date('z')) . ' days');
            break;

            case 'ALL_TIME':
                $date->modify('-999 years');
            break;

            default:
                return $defaultDate;
        }

        $date->setTimeZone($targetTZ);

        return $date;
    }

    public function endDate($dateRangeStr, $timezoneStr)
    {
        $userTZ    = new DateTimeZone($timezoneStr); 
        $targetTZ  = new DateTimeZone('UTC');

        $defaultDate = new DateTime(date('Y-m-d') . ' 00:00:00', $userTZ);
        $defaultDate->setTimeZone($targetTZ);
        $defaultDate->modify('+1 day');

        if( ! $dateRangeStr )
            return $defaultDate;

        $dateRange = json_decode($dateRangeStr);
        if( $dateRange->key === 'CUSTOM' ){
            if( ! isset($dateRange->end_date) )
                return $defaultDate;

            $date = DateTime::createFromFormat('Y-m-d H:i:s', trim($dateRange->end_date) . ' 00:00:00', $userTZ);
            if( ! $date )
                return $defaultDate;
            
            $date->setTimeZone($targetTZ);
            $date->modify('+1 day');
            
        
            return $date;
        }

        //  Preset
        //
        return $defaultDate;
    }   
    /**
     * Determine end date
     * 
     */
    public function _endDate(object $dateFilter, string $timezone)
    {
        $timezone       = new DateTimeZone($timezone);
        $targetTimezone = new DateTimeZone('UTC');

        //  If it's a static date, there's not much to do
        if( ! empty($dateFilter->input) ){
            //  Apply their timezone
            $date = new DateTime($dateFilter->input, $timezone);
            $date = new DateTime($date->format('Y-m-d 23:59:59'), $timezone);
            $date->setTimeZone($targetTimezone);

            return $date;
        }

        //  Get current UTC date
        $date = new DateTime();

        //  Apply their timezone
        $date->setTimeZone($timezone);

        switch( $dateFilter->date ){
            case 'CURRENT_DAY':
                $date->modify('today');
            break; 

            case 'YESTERDAY':
                $date->modify('yesterday');
            break;

            case 'THIS_WEEK':
                $date->modify('this week ' . strtolower($dateFilter->interval));
            break;

            case 'LAST_WEEK':
                $date->modify('last week ' . strtolower($dateFilter->interval));
            break;

            case 'THIS_MONTH':
                $date->modify('last day of this month');
            break;

            case 'LAST_MONTH':
                $date->modify('last day of last month');
            break;

            case 'THIS_YEAR':
                $date = new DateTime($date->format('Y-12-31'), $timezone);
            break;

            case 'LAST_YEAR':
                $date->modify('-1 year');

                $date = new DateTime($date->format('Y-12-31'), $timezone);
            break;

            case 'FIRST_DAY_OF':
                switch($dateFilter->interval){
                    //  Week
                    case 'THIS_WEEK':
                        $dayOfWeek = $date->format('w');

                        $date->modify('-' . $dayOfWeek . ' days');
                    break;
                    case 'LAST_WEEK':
                        $date->modify('-1 week');

                        $dayOfWeek = $date->format('w');

                        $date->modify('-' . $dayOfWeek . ' days');
                    break;

                    //  Month
                    case 'THIS_MONTH':
                        $dayOfMonth = $date->format('j');

                        $date->modify('-' . $dayOfMonth . ' days');
                    break;

                    case 'LAST_MONTH':
                        $date->modify('-1 month');

                        $dayOfMonth = $date->format('j');

                        $date->modify('-' . $dayOfMonth . ' days');
                    break;

                    //  Year
                    case 'THIS_YEAR':
                        $date = new DateTime($date->format('Y-01-01'), $timezone);
                    break;

                    case 'LAST_YEAR':
                        $date->modify('-1 year');
                        $date = new DateTime($date->format('Y-01-01'), $timezone);
                    break;
                }
            break;



            /* Last day of */
            case 'LAST_DAY_OF':
                switch($dateFilter->interval){
                    //  Week
                    case 'THIS_WEEK':
                        $dayOfWeek = $date->format('w');
                        
                        $offset    = 7 - $dayOfWeek;  

                        $date->modify('+' . $offset . ' days');
                    break;
                    case 'LAST_WEEK':
                        $date->modify('-1 week');

                        $dayOfWeek = $date->format('w');
                        
                        $offset    = 7 - $dayOfWeek;  

                        $date->modify('+' . $offset . ' days');
                    break;

                    //  Month
                    case 'THIS_MONTH':
                        $dayOfMonth  = $date->format('j');
                        $daysInMonth = $date->format('t');
                        $offset      = $daysInMonth - $dayOfMonth;

                        $date->modify('+' . $offset . ' days');
                    break;

                    case 'LAST_MONTH':
                        $date->modify('-1 month');

                        $dayOfMonth  = $date->format('j');
                        $daysInMonth = $date->format('t');
                        $offset      = $daysInMonth - $dayOfMonth;

                        $date->modify('+' . $offset . ' days');
                        
                    break;

                    //  Year
                    case 'THIS_YEAR':
                        $date = new DateTime($date->format('Y-12-31'), $timezone);
                    break;

                    case 'LAST_YEAR':
                        $date = new DateTime($date->format('Y-12-31'), $timezone);
                        $date->modify('-1 year');
                    break;
                }
            break;



            /* First week of */
            case 'FIRST_WEEK_OF':
                switch($dateFilter->interval){
                    //  Month 
                    case 'THIS_MONTH':
                        $dayOfMonth = $date->format('j');

                        $date->modify('-' . $dayOfMonth . ' days');

                        $date->modify('+1 week');
                    break;
                    
                    case 'LAST_MONTH':
                        $date->modify('-1 month');

                        $dayOfMonth = $date->format('j');

                        $date->modify('-' . $dayOfMonth . ' days');

                        $date->modify('+1 week');
                    break;

                    //  Year
                    case 'THIS_YEAR':
                        $date = new DateTime($date->format('Y-01-01'), $timezone);

                        $date->modify('+1 week');
                    break;

                    case 'LAST_YEAR':
                        $date->modify('-1 year');

                        $date = new DateTime($date->format('Y-01-01'), $timezone);

                        $date->modify('+1 week');
                    break;
                }
            break;



            case 'LAST_WEEK_OF':
                switch($dateFilter->interval){
                    //  Month
                    case 'THIS_MONTH':
                        $dayOfMonth  = $date->format('j');
                        $daysInMonth = $date->format('t');
                        $offset      = $daysInMonth - $dayOfMonth;

                        $date->modify('+' . $offset . ' days');
                        $date->modify('-1 week');
                    break;
                    case 'LAST_MONTH':
                        $date->modify('-1 month');

                        $dayOfMonth  = $date->format('j');
                        $daysInMonth = $date->format('t');
                        $offset      = $daysInMonth - $dayOfMonth;

                        $date->modify('+' . $offset . ' days');
                        $date->modify('-1 week');
                    break;

                    //  Year
                    case 'THIS_YEAR':
                        $date = new DateTime($date->format('Y-12-31'), $timezone);
                        $date->modify('-1 week');
                    break;

                    case 'LAST_YEAR':
                        $date = new DateTime($date->format('Y-12-31'), $timezone);
                        $date->modify('-1 year');
                        $date->modify('-1 week');
                    break;
                }
            break;



            case 'FIRST_MONTH_OF':
                switch($dateFilter->interval){
                    //  Year
                    case 'THIS_YEAR':
                        $date = new DateTime($date->format('Y-01-31'), $timezone);
                    break;

                    case 'LAST_YEAR':
                        $date->modify('-1 year');

                        $date = new DateTime($date->format('Y-01-31'), $timezone);
                    break;
                }
            break;



            case 'LAST_MONTH_OF':
                switch($dateFilter->interval){
                    //  Year
                    case 'THIS_YEAR':
                        $date = new DateTime($date->format('Y-12-31'), $timezone);
                    break;

                    case 'LAST_YEAR':
                        $date = new DateTime($date->format('Y-12-31'), $timezone);
                        $date->modify('-1 year');
                    break;
                }
            break;


            case 'PRIOR':
                $date->modify('-' . $dateFilter->prior->time_frame . ' ' . strtolower($dateFilter->prior->interval));
            break;
        }

        //  Move to last hour of the day
        $date = new DateTime($date->format('Y-m-d' . ' 23:59:59'), $timezone);
        
        //  Go back to UTC
        $date->setTimeZone($targetTimezone);

        return $date;
    }



    /**
     * Determine start date
     * 
     */
    public function _startDate(object $dateFilter, DateTime $endDate, string $timezone)
    {
        $timezone       = new DateTimeZone($timezone);
        $targetTimezone = new DateTimeZone('UTC');

        //  If it's a static date, there's not much to do
        if( ! empty($dateFilter->input) ){
            //  Apply their timezone
            $date = new DateTime($dateFilter->input, $timezone);
            $date = new DateTime($date->format('Y-m-d 00:00:00'), $timezone);
            $date->setTimeZone($targetTimezone);

            return $date;
        }

        //  Get current UTC date
        $date = new DateTime();

        //  Apply their timezone
        $date->setTimeZone($timezone);

        switch( $dateFilter->date ){
            case 'CURRENT_DAY':
                $date->modify('today');
            break; 

            case 'YESTERDAY':
                $date->modify('yesterday');
            break;

            case 'THIS_WEEK':
                $date->modify('this week ' . strtolower($dateFilter->interval));
            break;

            case 'LAST_WEEK':
                $date->modify('last week ' . strtolower($dateFilter->interval));
            break;

            case 'THIS_MONTH':
                $date->modify('first day of this month');
            break;

            case 'LAST_MONTH':
                $date->modify('first day of last month');
            break;

            case 'THIS_YEAR':
                $date = new DateTime($date->format('Y-01-01'), $timezone);
            break;

            case 'LAST_YEAR':
                $date->modify('-1 year');

                $date = new DateTime($date->format('Y-01-01'), $timezone);
            break;

            case 'FIRST_DAY_OF':
                switch($dateFilter->interval){
                    //  Week
                    case 'THIS_WEEK':
                        $dayOfWeek = $date->format('w');

                        $date->modify('-' . $dayOfWeek . ' days');
                    break;
                    case 'LAST_WEEK':
                        $date->modify('-1 week');

                        $dayOfWeek = $date->format('w');

                        $date->modify('-' . $dayOfWeek . ' days');
                    break;

                    //  Month
                    case 'THIS_MONTH':
                        $dayOfMonth = $date->format('j');

                        $date->modify('-' . $dayOfMonth . ' days');
                    break;

                    case 'LAST_MONTH':
                        $date->modify('-1 month');

                        $dayOfMonth = $date->format('j');

                        $date->modify('-' . $dayOfMonth . ' days');
                    break;

                    //  Year
                    case 'THIS_YEAR':
                        $date = new DateTime($date->format('Y-01-01'), $timezone);
                    break;

                    case 'LAST_YEAR':
                        $date->modify('-1 year');
                        $date = new DateTime($date->format('Y-01-01'), $timezone);
                    break;
                }
            break;



            /* Last day of */
            case 'LAST_DAY_OF':
                switch($dateFilter->interval){
                    //  Week
                    case 'THIS_WEEK':
                        $dayOfWeek = $date->format('w');
                        
                        $offset    = 7 - $dayOfWeek;  

                        $date->modify('+' . $offset . ' days');
                    break;
                    case 'LAST_WEEK':
                        $date->modify('-1 week');

                        $dayOfWeek = $date->format('w');
                        
                        $offset    = 7 - $dayOfWeek;  

                        $date->modify('+' . $offset . ' days');
                    break;

                    //  Month
                    case 'THIS_MONTH':
                        $dayOfMonth  = $date->format('j');
                        $daysInMonth = $date->format('t');
                        $offset      = $daysInMonth - $dayOfMonth;

                        $date->modify('+' . $offset . ' days');
                    break;

                    case 'LAST_MONTH':
                        $date->modify('-1 month');

                        $dayOfMonth  = $date->format('j');
                        $daysInMonth = $date->format('t');
                        $offset      = $daysInMonth - $dayOfMonth;

                        $date->modify('+' . $offset . ' days');
                        
                    break;

                    //  Year
                    case 'THIS_YEAR':
                        $date = new DateTime($date->format('Y-12-31'), $timezone);
                    break;

                    case 'LAST_YEAR':
                        $date = new DateTime($date->format('Y-12-31'), $timezone);
                        $date->modify('-1 year');
                    break;
                }
            break;



            /* First week of */
            case 'FIRST_WEEK_OF':
                switch($dateFilter->interval){
                    //  Month 
                    case 'THIS_MONTH':
                        $dayOfMonth = $date->format('j');

                        $date->modify('-' . $dayOfMonth . ' days');

                        $date->modify('+1 week');
                    break;
                    
                    case 'LAST_MONTH':
                        $date->modify('-1 month');

                        $dayOfMonth = $date->format('j');

                        $date->modify('-' . $dayOfMonth . ' days');

                        $date->modify('+1 week');
                    break;

                    //  Year
                    case 'THIS_YEAR':
                        $date = new DateTime($date->format('Y-01-01'), $timezone);

                        $date->modify('+1 week');
                    break;

                    case 'LAST_YEAR':
                        $date->modify('-1 year');

                        $date = new DateTime($date->format('Y-01-01'), $timezone);

                        $date->modify('+1 week');
                    break;
                }
            break;



            case 'LAST_WEEK_OF':
                switch($dateFilter->interval){
                    //  Month
                    case 'THIS_MONTH':
                        $dayOfMonth  = $date->format('j');
                        $daysInMonth = $date->format('t');
                        $offset      = $daysInMonth - $dayOfMonth;

                        $date->modify('+' . $offset . ' days');
                        $date->modify('-1 week');
                    break;
                    case 'LAST_MONTH':
                        $date->modify('-1 month');

                        $dayOfMonth  = $date->format('j');
                        $daysInMonth = $date->format('t');
                        $offset      = $daysInMonth - $dayOfMonth;

                        $date->modify('+' . $offset . ' days');
                        $date->modify('-1 week');
                    break;

                    //  Year
                    case 'THIS_YEAR':
                        $date = new DateTime($date->format('Y-12-31'), $timezone);
                        $date->modify('-1 week');
                    break;

                    case 'LAST_YEAR':
                        $date = new DateTime($date->format('Y-12-31'), $timezone);
                        $date->modify('-1 year');
                        $date->modify('-1 week');
                    break;
                }
            break;



            case 'FIRST_MONTH_OF':
                switch($dateFilter->interval){
                    //  Year
                    case 'THIS_YEAR':
                        $date = new DateTime($date->format('Y-01-01'), $timezone);
                    break;

                    case 'LAST_YEAR':
                        $date->modify('-1 year');

                        $date = new DateTime($date->format('Y-01-01'), $timezone);
                    break;
                }
            break;



            case 'LAST_MONTH_OF':
                switch($dateFilter->interval){
                    //  Year
                    case 'THIS_YEAR':
                        $date = new DateTime($date->format('Y-12-01'), $timezone);
                    break;

                    case 'LAST_YEAR':
                        $date = new DateTime($date->format('Y-12-01'), $timezone);
                        $date->modify('-1 year');
                    break;
                }
            break;


            case 'PRIOR':
                $date = clone $endDate;

                $date->modify('-' . $dateFilter->prior->time_frame . ' ' . strtolower($dateFilter->prior->interval));
            break;
        }

        //  Move to last hour of the day
        $date = new DateTime($date->format('Y-m-d' . ' 00:00:00'), $timezone);
        
        //  Go back to UTC
        $date->setTimeZone($targetTimezone);

        return $date;
    }

}