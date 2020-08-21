<?php

namespace Tests\Unit\Company;

use Tests\TestCase;
use \App\Models\Company\Report;
use \App\Models\Company\ScheduledExport;

class ScheduledExportTest extends TestCase
{
    use \Tests\CreatesAccount;

    /**
     * Test run at date calculates properly
     *
     * @group scheduled-exports
     */
    public function testNextRunAtMethod()
    {
        $company = $this->createCompany();
        
        $report  = factory(Report::class)->create([
            'account_id' => $this->account->id,
            'company_id' => $company->id,
            'created_by' => $this->user->id,
        ]); 

        //  Test previous day
        $userTimeNow    = now()->setTimeZone($this->user->timezone);
        $userYesterday  = (clone $userTimeNow)->subDays(1)->subHours(1);
        
        $schedule = factory(ScheduledExport::class)->create([
            'company_id'  => $company->id,
            'report_id'   => $report->id,
            'day_of_week' => (clone $userYesterday)->format('N'),
            'hour_of_day' => (clone $userYesterday)->format('G')
        ]);

        $nextRunAt = ScheduledExport::nextRunAt($schedule->day_of_week, $schedule->hour_of_day, $this->user->timezone);
        $diff      = $userYesterday->diff($nextRunAt);
        $this->assertEquals($diff->days, 6);


        //  Test current early day 
        $userTimeNow    = now()->setTimeZone($this->user->timezone);
        $userTimeEarly  = (clone $userTimeNow);
        
        $schedule = factory(ScheduledExport::class)->create([
            'company_id'  => $company->id,
            'report_id'   => $report->id,
            'day_of_week' => (clone $userTimeEarly)->format('N'),
            'hour_of_day' => (clone $userTimeEarly)->format('G')
        ]);

        $nextRunAt = ScheduledExport::nextRunAt($schedule->day_of_week, $schedule->hour_of_day, $this->user->timezone);
        $diff      = $userTimeEarly->diff($nextRunAt);

        $this->assertEquals($diff->days, 0);
        $this->assertEquals($diff->h, 0);

        //  Test current later day 
        $userTimeNow    = now()->setTimeZone($this->user->timezone);
        $userTimeLate  = (clone $userTimeNow)->subHours(1);
        
        $schedule = factory(ScheduledExport::class)->create([
            'company_id'  => $company->id,
            'report_id'   => $report->id,
            'day_of_week' => now()->setTimeZone($this->user->timezone)->format('N'),
            'hour_of_day' => (clone $userTimeLate)->format('G')
        ]);
        
        $nextRunAt = ScheduledExport::nextRunAt($schedule->day_of_week, intval($schedule->hour_of_day), $this->user->timezone);
        $diff      = $userTimeLate->diff($nextRunAt);

        $this->assertEquals($diff->days, 6);
        $this->assertEquals($diff->h, 23);
    }
}
