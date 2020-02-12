<?php
namespace App\Traits\Helpers;
use App\Models\Company;
use App\Helpers\Formatter;
use DateTime;
use DateTimeZone;
use Exception;

trait HandlesDateFilters
{ 
    /**
     * Determine end date
     * 
     */
    public function endDate(object $dateFilter, string $timezone)
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
    public function startDate(object $dateFilter, DateTime $endDate, string $timezone)
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