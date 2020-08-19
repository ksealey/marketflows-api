<?php
namespace App\Traits\Helpers;
use App\Models\Company;
use App\Helpers\Formatter;
use DateTime;
use DateTimeZone;
use Exception;
use \Carbon\Carbon;
use \Validator;
use \DB;

trait HandlesDateFilters
{ 
    public function getDateFilterDateTypes()
    {
        return [
            'CUSTOM',
            'YESTERDAY',
            'TODAY',
            'LAST_7_DAYS',
            'LAST_30_DAYS',
            'LAST_60_DAYS',
            'LAST_90_DAYS',
            'LAST_180_DAYS',
            'LAST_N_DAYS',
            'ALL_TIME'
        ];
    }


    public function getDateFilterValidator($input, $additionalRules = [])
    {
        $rules = array_merge([
            'date_type' => 'bail|required|in:' . implode(',', $this->getDateFilterDateTypes()),
        ], $additionalRules);
        
        $validator = Validator::make($input, $rules);
        $validator->sometimes('start_date', 'bail|required|date', function($input){
            return $input->date_type === 'CUSTOM';
        });
        $validator->sometimes('end_date', 'bail|required|date|after_or_equal:start_date', function($input){
            return $input->date_type === 'CUSTOM';
        });
        $validator->sometimes('last_n_days', 'bail|required|numeric|min:1|max:730', function($input){
            return $input->date_type === 'LAST_N_DAYS';
        });

        return $validator;
    }



    public function getDateFilterDates($dateType, $timezone, $startDate = null, $endDate = null)
    {
        switch( $dateType ){
            case 'CUSTOM':
                $startDate = (new Carbon($startDate, $timezone));
                $endDate   = (new Carbon($endDate, $timezone));
            break;

            case 'YESTERDAY':
                $startDate = now()->setTimeZone($timezone)->subDays(1);
                $endDate   = (clone $startDate);
            break;

            case 'LAST_7_DAYS':
                $endDate   = now()->setTimeZone($timezone)->subDays(1);
                $startDate = (clone $endDate)->subDays(6);
            break;

            case 'LAST_30_DAYS':
                $endDate   = now()->setTimeZone($timezone)->subDays(1);
                $startDate = (clone $endDate)->subDays(29);
            break;

            case 'LAST_60_DAYS':
                $endDate   = now()->setTimeZone($timezone)->subDays(1);
                $startDate = (clone $endDate)->subDays(59);
            break;

            case 'LAST_90_DAYS':
                $endDate   = now()->setTimeZone($timezone)->subDays(1)->endOfDay();
                $startDate = (clone $endDate)->subDays(89);
            break;

            case 'LAST_180_DAYS':
                $endDate   = now()->setTimeZone($timezone)->subDays(1)->endOfDay();
                $startDate = (clone $endDate)->subDays(179);
            break;

            default: // TODAY
                $startDate = now()->setTimeZone($timezone);
                $endDate   = (clone $startDate);
            break;
        }

        return $this->_prepDates($startDate, $endDate);
    }

    public function getAllTimeDates($startDate, $timezone)
    {
        $endDate   = now()->setTimeZone($timezone);
        $startDate = (new Carbon($startDate))->setTimeZone($timezone);

        return $this->_prepDates($startDate, $endDate);
    }

    public function getLastNDaysDates($lastNDays, $timezone)
    {
        $endDate   = now()->setTimeZone($timezone)->subDays(1);
        $startDate = (clone $endDate)->subDays($lastNDays - 1);

        return $this->_prepDates($startDate, $endDate);
    }

    public function getPreviousDateFilterPeriod($startDate, $endDate)
    {
        $diff            = $startDate->diff($endDate);
        $vsEndDate       = (clone $startDate)->subDays(1);
        $vsStartDate     = (clone $vsEndDate)->subDays($diff->days);
        
        return $this->_prepDates($vsStartDate, $vsEndDate);
    }

    private function _prepDates($startDate, $endDate)
    {
        $startDate = $startDate->startOfDay();
        $endDate   = $endDate->endOfDay();

        return [ $startDate, $endDate ];
    }
}