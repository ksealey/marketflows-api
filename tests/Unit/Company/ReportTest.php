<?php

namespace Tests\Unit\Company;

use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use \App\Models\Company\Report;
use \App\Models\Company\Contact;
use \App\Models\Company\Call;

class ReportTest extends TestCase
{
    use \Tests\CreatesAccount, WithFaker;

    /**
     * Test count report can run
     * 
     * @group reports
     */
    public function testCountReportCanRun()
    {
        $company        = $this->createCompany([
            'created_at'    => now()->subDays(50)
        ]);
        $config         = $this->createConfig($company);
        $phoneNumber    = $this->createPhoneNumber($company, $config);
        
        factory(Contact::class, 4)->create([
            'account_id' => $company->account_id,
            'company_id' => $company->id
        ])->each(function($contact) use($phoneNumber){
            //  Create two in date range
            factory(Call::class)->create([
                'account_id'      => $contact->account_id,
                'company_id'      => $contact->company_id,
                'contact_id'      => $contact->id,
                'phone_number_id' => $phoneNumber->id,
                'phone_number_name' => $phoneNumber->name,
                'created_at'      => now()->subDays(2)
            ]);

            factory(Call::class)->create([
                'account_id'      => $contact->account_id,
                'company_id'      => $contact->company_id,
                'contact_id'      => $contact->id,
                'phone_number_id' => $phoneNumber->id,
                'phone_number_name' => $phoneNumber->name,
                'created_at'      => now()->subDays(3)
            ]);

            //  Create two out date range
            factory(Call::class)->create([
                'account_id'      => $contact->account_id,
                'company_id'      => $contact->company_id,
                'contact_id'      => $contact->id,
                'phone_number_id' => $phoneNumber->id,
                'phone_number_name' => $phoneNumber->name,
                'created_at'      => now()->subDays(9)
            ]);

            factory(Call::class)->create([
                'account_id'      => $contact->account_id,
                'company_id'      => $contact->company_id,
                'contact_id'      => $contact->id,
                'phone_number_id' => $phoneNumber->id,
                'phone_number_name' => $phoneNumber->name,
                'created_at'      => now()->subDays(10)
            ]);
        });

        $report  = factory(Report::class)->create([
            'account_id' => $this->account->id,
            'company_id' => $company->id,
            'created_by' => $this->user->id,
            'date_type'  => 'LAST_N_DAYS',
            'last_n_days' => 7
        ]);

        $results = $report->run();
        $this->assertEquals(count($results), 8);

        $report->last_n_days = 10;
        $report->save();

        $results = $report->run();
        $this->assertEquals(count($results), 16);
    }

    /**
     * Test timeframe report can run
     * 
     * @group reports
     */
    public function testTimeframeReportCanRun()
    {
        $company        = $this->createCompany([
            'created_at'    => now()->subDays(50)
        ]);
        $config         = $this->createConfig($company);
        $phoneNumber    = $this->createPhoneNumber($company, $config);
        
        factory(Contact::class, 4)->create([
            'account_id' => $company->account_id,
            'company_id' => $company->id
        ])->each(function($contact) use($phoneNumber){
            //  Create two in date range
            factory(Call::class)->create([
                'account_id'      => $contact->account_id,
                'company_id'      => $contact->company_id,
                'contact_id'      => $contact->id,
                'phone_number_id' => $phoneNumber->id,
                'phone_number_name' => $phoneNumber->name,
                'created_at'      => now()->subDays(2)
            ]);

            factory(Call::class)->create([
                'account_id'      => $contact->account_id,
                'company_id'      => $contact->company_id,
                'contact_id'      => $contact->id,
                'phone_number_id' => $phoneNumber->id,
                'phone_number_name' => $phoneNumber->name,
                'created_at'      => now()->subDays(3)
            ]);

            //  Create two out date range
            factory(Call::class)->create([
                'account_id'      => $contact->account_id,
                'company_id'      => $contact->company_id,
                'contact_id'      => $contact->id,
                'phone_number_id' => $phoneNumber->id,
                'phone_number_name' => $phoneNumber->name,
                'created_at'      => now()->subDays(9)
            ]);

            factory(Call::class)->create([
                'account_id'      => $contact->account_id,
                'company_id'      => $contact->company_id,
                'contact_id'      => $contact->id,
                'phone_number_id' => $phoneNumber->id,
                'phone_number_name' => $phoneNumber->name,
                'created_at'      => now()->subDays(10)
            ]);
        });

        $report  = factory(Report::class)->create([
            'account_id' => $this->account->id,
            'company_id' => $company->id,
            'created_by' => $this->user->id,
            'type'       => 'timeframe', 
            'date_type'  => 'LAST_N_DAYS',
            'last_n_days' => 7
        ]);

        $results = $report->run();
        $this->assertEquals(count($results), 8);

        $report->last_n_days = 10;
        $report->save();

        $results = $report->run();
        $this->assertEquals(count($results), 16);
    }

    /**
     * Test exporting count report
     * 
     * @group reports
     */
    public function testExportCountReport()
    {
        $company        = $this->createCompany([
            'created_at'    => now()->subDays(50)
        ]);
        $config         = $this->createConfig($company);
        $phoneNumber    = $this->createPhoneNumber($company, $config);
        
        factory(Contact::class, 4)->create([
            'account_id' => $company->account_id,
            'company_id' => $company->id
        ])->each(function($contact) use($phoneNumber){
            
            factory(Call::class)->create([
                'account_id'      => $contact->account_id,
                'company_id'      => $contact->company_id,
                'contact_id'      => $contact->id,
                'phone_number_id' => $phoneNumber->id,
                'phone_number_name' => $phoneNumber->name,
                'created_at'      => now()->subDays(2)
            ]);

            factory(Call::class)->create([
                'account_id'      => $contact->account_id,
                'company_id'      => $contact->company_id,
                'contact_id'      => $contact->id,
                'phone_number_id' => $phoneNumber->id,
                'phone_number_name' => $phoneNumber->name,
                'created_at'      => now()->subDays(3)
            ]);

            factory(Call::class)->create([
                'account_id'      => $contact->account_id,
                'company_id'      => $contact->company_id,
                'contact_id'      => $contact->id,
                'phone_number_id' => $phoneNumber->id,
                'phone_number_name' => $phoneNumber->name,
                'created_at'      => now()->subDays(9)
            ]);

            factory(Call::class)->create([
                'account_id'      => $contact->account_id,
                'company_id'      => $contact->company_id,
                'contact_id'      => $contact->id,
                'phone_number_id' => $phoneNumber->id,
                'phone_number_name' => $phoneNumber->name,
                'created_at'      => now()->subDays(10)
            ]);
        });

        $report  = factory(Report::class)->create([
            'account_id' => $this->account->id,
            'company_id' => $company->id,
            'created_by' => $this->user->id,
            'type'       => 'count', 
            'group_by'   => 'call_source',
            'date_type'  => 'ALL_TIME'
        ]);

        $report->export(true);

        $this->assertNotNull($report->writePath);
        $this->assertTrue(file_exists($report->writePath));
    }

    /**
     * Test exporting timeframe report
     * 
     * @group reports
     */
    public function testExportTimeframeReport()
    {
        $company        = $this->createCompany([
            'created_at'    => now()->subDays(50)
        ]);
        $config         = $this->createConfig($company);
        $phoneNumber    = $this->createPhoneNumber($company, $config);
        
        factory(Contact::class, 4)->create([
            'account_id' => $company->account_id,
            'company_id' => $company->id
        ])->each(function($contact) use($phoneNumber){
            
            factory(Call::class)->create([
                'account_id'      => $contact->account_id,
                'company_id'      => $contact->company_id,
                'contact_id'      => $contact->id,
                'phone_number_id' => $phoneNumber->id,
                'phone_number_name' => $phoneNumber->name,
                'created_at'      => now()->subDays(2)
            ]);

            factory(Call::class)->create([
                'account_id'      => $contact->account_id,
                'company_id'      => $contact->company_id,
                'contact_id'      => $contact->id,
                'phone_number_id' => $phoneNumber->id,
                'phone_number_name' => $phoneNumber->name,
                'created_at'      => now()->subDays(3)
            ]);

            factory(Call::class)->create([
                'account_id'      => $contact->account_id,
                'company_id'      => $contact->company_id,
                'contact_id'      => $contact->id,
                'phone_number_id' => $phoneNumber->id,
                'phone_number_name' => $phoneNumber->name,
                'created_at'      => now()->subDays(9)
            ]);

            factory(Call::class)->create([
                'account_id'      => $contact->account_id,
                'company_id'      => $contact->company_id,
                'contact_id'      => $contact->id,
                'phone_number_id' => $phoneNumber->id,
                'phone_number_name' => $phoneNumber->name,
                'created_at'      => now()->subDays(10)
            ]);
        });

        $report  = factory(Report::class)->create([
            'account_id' => $this->account->id,
            'company_id' => $company->id,
            'created_by' => $this->user->id,
            'type'       => 'timeframe',
            'group_by'   => null, 
            'date_type'  => 'ALL_TIME'
        ]);

        $report->export(true);

        $this->assertNotNull($report->writePath);
        $this->assertTrue(file_exists($report->writePath));
    }


}
