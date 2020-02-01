<?php
namespace App\Traits\Helpers;
use App\Models\Company;
use App\Helpers\Formatter;
use DateTime;
use DateTimeZone;

trait AppendsDates
{ 
    public function withAppendedDates($timezone, $records = [])
    {
        //  Set local time
        $now             = new DateTime(); 
        $defaultTimezone = new DateTimeZone('UTC');
        $targetTimezone  = new DateTimeZone($timezone);

        foreach( $records as $record ){
            $fields = property_exists($this, 'appendedDates') ? $record->appendedDates : ['created_at', 'updated_at'];

            $offsetTimes        = [];
            $localTimes         = [];
            $localTimesFriendly = [];

            //  Apply to each field
            foreach( $fields as $field ){
                //  Get the date of the field provided 
                $recordDate = new DateTime($record->$field, $defaultTimezone);

                //  Get the offset time of field
                $offsetTimes[$field] = Formatter::offsetTimeString($now->diff($recordDate));

                //  Get the local time of the field
                $recordDate->setTimeZone($targetTimezone);

                $localTimes[$field]  =  $recordDate->format('Y-m-d H:i:s');

                //  Get a friendly version of the time fiel
                $localTimesFriendly[$field]  =  $recordDate->format('F jS, Y') . ' at ' . $recordDate->format('g:ia');
            }

            $record->offset_times         = $offsetTimes;
            $record->local_times          = $localTimes;
            $record->local_times_friendly = $localTimesFriendly;
            
        }

        return $records;
    }
}