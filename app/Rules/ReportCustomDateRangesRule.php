<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use DateTime;

class ReportCustomDateRangesRule implements Rule
{
    protected $message = '';

    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        $dateRanges = json_decode($value);
        if( ! is_array($dateRanges) ){
            $this->message = 'Date ranges must be provided as a json array.';

            return false;
        }

        if( ! count($dateRanges) ){
            $this->message = 'At least 1 date range is required.';

            return false;
        }

        if( count($dateRanges) > 4 ){
            $this->message = $attribute . ' cannot have more than 4 date ranges.';

            return false;
        }

        $dateRangeDiff = null;
        foreach( $dateRanges as $idx => $dateRange ){
            if( empty($dateRange->start) ){
                $this->message = $attribute . ' missing start property at index ' . $idx . '.';
                return false;
            }

            if( empty($dateRange->end) ){
                $this->message = $attribute . ' missing end property at index ' . $idx . '.';
                return false;
            }

            $startDate = DateTime::createFromFormat('Y-m-d', $dateRange->start);
            if( ! $startDate ){
                $this->message = 'Start date is invalid for date range at index ' . $idx . '. Date should be formatted as Y-m-d.';
                return false;
            }

            $endDate = DateTime::createFromFormat('Y-m-d', $dateRange->end);
            if( ! $endDate ){
                $this->message = 'End date is invalid for date range at index ' . $idx . '. Date should be formatted as Y-m-d.';
                return false;
            }
            
            if( $startDate->format('U') > $endDate->format('U') ){
                $this->message = 'Start date cannot be after end date for date range at index ' . $idx . '.';
                return false;
            }

            $dateDiff = $startDate->diff($endDate);
            if( $dateDiff->days > 90 ){
                $this->message = 'Date range cannot exceed 90 days.';
                return false;
            }
            
            if( $dateRangeDiff ){
                if( $dateDiff->days !== $dateRangeDiff->days ){
                    $this->message = 'Date ranges must be equivalent, having an equal amount of days.';
                    return false;
                }
            }

            $dateRangeDiff = $dateDiff;
        }

        return true;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return $this->message;
    }
}
