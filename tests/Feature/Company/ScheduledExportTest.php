<?php

namespace Tests\Feature\Company;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use \App\Models\Company\Report;
use \App\Models\Company\ScheduledExport;
use \App\Jobs\ExecuteScheduledExportJob;
use \App\Mail\ScheduledExport as ScheduledExportMail;
use Queue;
use Mail;
use Artisan;

class ScheduledExportTest extends TestCase
{
    use \Tests\CreatesAccount, WithFaker;

    /**
     * Test creating scheduled exports
     * 
     * @group scheduled-exports
     */
    public function testCreate()
    {
        $company = $this->createCompany();
        $report  = factory(Report::class)->create([
            'account_id' => $this->account->id,
            'company_id' => $company->id,
            'created_by' => $this->user->id
        ]); 

        $schedule = factory(ScheduledExport::class)->make();
        $response = $this->json('POST', route('create-scheduled-export', [
            'company' => $company->id
        ]), [
            'report_id'   => $report->id,
            'day_of_week' => $schedule->day_of_week,
            'hour_of_day' => $schedule->hour_of_day,
            'timezone'        => $schedule->timezone,
            'delivery_method' => $schedule->delivery_method,
            'delivery_email_addresses' => implode(',', $schedule->delivery_email_addresses)
        ]);

        $response->assertStatus(201);
        $response->assertJSON([
            "company_id"    => $company->id,
            "report_id"     => $report->id,
            "day_of_week"   => $schedule->day_of_week,
            "hour_of_day"   => $schedule->hour_of_day,
            "delivery_method" => $schedule->delivery_method,
            "delivery_email_addresses" => $schedule->delivery_email_addresses
        ]);
    }

    /**
     * Test read
     * 
     * @group scheduled-exports
     */
    public function testRead()
    {
        $company = $this->createCompany();
        $report  = factory(Report::class)->create([
            'account_id' => $this->account->id,
            'company_id' => $company->id,
            'created_by' => $this->user->id
        ]); 

        $schedule = factory(ScheduledExport::class)->create([
            'company_id' => $company->id,
            'report_id' => $report->id
        ]);

        $response = $this->json('GET', route('read-scheduled-export', [
            'company'         => $company->id,
            'scheduledExport' => $schedule->id
        ]));

        $response->assertStatus(200);
        $response->assertJSON([
            "company_id"    => $company->id,
            "report_id"     => $report->id,
            "day_of_week"   => $schedule->day_of_week,
            "hour_of_day"   => $schedule->hour_of_day,
            "timezone"        =>  $schedule->timezone,
            "delivery_method" => $schedule->delivery_method,
            "delivery_email_addresses" => $schedule->delivery_email_addresses
        ]);
    }

    /**
     * Test update
     * 
     * @group scheduled-exports
     */
    public function testUpdate()
    {
        $company = $this->createCompany();
        $report  = factory(Report::class)->create([
            'account_id' => $this->account->id,
            'company_id' => $company->id,
            'created_by' => $this->user->id
        ]); 
        $report2  = factory(Report::class)->create([
            'account_id' => $this->account->id,
            'company_id' => $company->id,
            'created_by' => $this->user->id
        ]); 

        $schedule = factory(ScheduledExport::class)->create([
            'company_id' => $company->id,
            'report_id'  => $report->id
        ]);

        $scheduleUpdate = factory(ScheduledExport::class)->make();

        $response = $this->json('PUT', route('read-scheduled-export', [
            'company'         => $company->id,
            'scheduledExport' => $schedule->id
        ]), [
            'report_id'   => $report2->id,
            'day_of_week' => $scheduleUpdate->day_of_week,
            'hour_of_day' => $scheduleUpdate->hour_of_day,
            'timezone'        => $scheduleUpdate->timezone,
            'delivery_method' => $scheduleUpdate->delivery_method,
            'delivery_email_addresses' => implode(',', $scheduleUpdate->delivery_email_addresses)
        ]);

        $response->assertStatus(200);
        $response->assertJSON([
            "company_id"    => $company->id,
            "report_id"     => $report2->id,
            "day_of_week"   => $scheduleUpdate->day_of_week,
            "hour_of_day"   => $scheduleUpdate->hour_of_day,
            "timezone"        =>  $scheduleUpdate->timezone,
            "delivery_method" => $scheduleUpdate->delivery_method,
            "delivery_email_addresses" => $scheduleUpdate->delivery_email_addresses
        ]);
    }

    /**
     * Test delete
     * 
     * @group scheduled-exports
     */
    public function testDelete()
    {
        $company = $this->createCompany();
        $report  = factory(Report::class)->create([
            'account_id' => $this->account->id,
            'company_id' => $company->id,
            'created_by' => $this->user->id
        ]); 

        $schedule = factory(ScheduledExport::class)->create([
            'company_id' => $company->id,
            'report_id' => $report->id
        ]);

        $response = $this->json('DELETE', route('read-scheduled-export', [
            'company' => $company->id,
            'scheduledExport' => $schedule->id
        ]));

        $response->assertStatus(200);
        $response->assertJSON([
            'message' => 'Deleted'
        ]);

        $this->assertDatabaseMissing('scheduled_exports', [
            'id' => $schedule->id
        ]);
    }

    /**
     * Test listing
     * 
     * @group scheduled-exports
     */
    public function testList()
    {
        $company = $this->createCompany();
        $report  = factory(Report::class)->create([
            'account_id' => $this->account->id,
            'company_id' => $company->id,
            'created_by' => $this->user->id
        ]); 

        $schedule = factory(ScheduledExport::class, 10)->create([
            'company_id' => $company->id,
            'report_id' => $report->id
        ]);

        $response = $this->json('GET', route('list-scheduled-exports', [
            'company' => $company->id,
            'date_type' => 'ALL_TIME'
        ]));

        $response->assertStatus(200);
        $response->assertJSON([
            "result_count" => 10,
            "limit"        => 250,
            "page"         => 1,
            "total_pages"  =>  1,
            "next_page"    => null,
        ]);

        $response->assertJSONStructure([
            "results" => [
                [
                    'company_id',
                    "report_id",
                    "day_of_week",
                    "hour_of_day",
                    "timezone",
                    "delivery_method",
                    "delivery_email_addresses"
                ]
            ]
        ]);
    }

    /**
     * Test that the scheduled report jobs are dispatched when called
     * 
     * @group scheduled-exports
     */
    public function testScheduledReportDispatched()
    {
        Queue::fake();

        $timezone = $this->faker()->timezone;
        ScheduledExport::where('timezone', $timezone)
                      ->where('day_of_week', now()->setTimeZone($timezone)->format('w'))
                      ->where('hour_of_day', now()->setTimeZone($timezone)->format('G'))
                      ->delete();

        $company = $this->createCompany();
        $report  = factory(Report::class)->create([
            'account_id' => $this->account->id,
            'company_id' => $company->id,
            'created_by' => $this->user->id,
        ]); 

        $schedule = factory(ScheduledExport::class)->create([
            'company_id'  => $company->id,
            'report_id'   => $report->id,
            'timezone'    => $timezone,
            'day_of_week' => now()->setTimeZone($timezone)->format('w'),
            'hour_of_day' => now()->setTimeZone($timezone)->format('G')
        ]);

        Artisan::call('push-scheduled-exports');

        Queue::assertPushed(ExecuteScheduledExportJob::class, function($job) use($report, $schedule){
            return $job->scheduledExport->id == $schedule->id 
                && $job->scheduledExport->report_id === $report->id;
        });
    }

    /**
     * Test job sends email
     * 
     * @group scheduled-exports
     */
    public function testScheduledReportMailedDispatched()
    {
        Mail::fake();

        $timezone = $this->faker()->timezone;
        ScheduledExport::where('timezone', $timezone)
                      ->where('day_of_week', now()->setTimeZone($timezone)->format('w'))
                      ->where('hour_of_day', now()->setTimeZone($timezone)->format('G'))
                      ->delete();

        $company = $this->createCompany();
        $report  = factory(Report::class)->create([
            'account_id' => $this->account->id,
            'company_id' => $company->id,
            'created_by' => $this->user->id,
        ]); 

        $schedule = factory(ScheduledExport::class)->create([
            'company_id'  => $company->id,
            'report_id'   => $report->id,
            'timezone'    => $timezone,
            'day_of_week' => now()->setTimeZone($timezone)->format('w'),
            'hour_of_day' => now()->setTimeZone($timezone)->format('G')
        ]);

        Artisan::call('push-scheduled-exports');

        Mail::assertSent(ScheduledExportMail::class, function($mail) use($report){
            return $mail->report->id === $report->id;
        });
    }
}
