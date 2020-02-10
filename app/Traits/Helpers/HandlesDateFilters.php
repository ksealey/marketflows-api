<?php
namespace App\Traits\Helpers;
use App\Models\Company;
use App\Helpers\Formatter;
use DateTime;
use DateTimeZone;

trait HandlesDateFilters
{ 
    public function startDate($startDate, $timezone)
    {
        return '2019-01-01';
    }

    public function endDate($endDate, $timezone, $startDate = null)
    {
        return '2020-03-01';
    }
}