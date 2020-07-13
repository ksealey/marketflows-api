<?php

namespace Tests\Unit;

use Tests\TestCase;
use \App\Models\Company\PhoneNumber;
use \App\Models\Company\Call;

class PhoneNumberTest extends TestCase
{
    use \Tests\CreatesAccount;

    /**
     * Test checking renewal days
     * 
     * @group phone-numbers-unit
     */
    public function testwillRenewBeforeDays()
    {
        $company = $this->createCompany();
        $config  = $this->createConfig($company);
        $phoneNumber = $this->createPhoneNumber($company, $config, [
            'purchased_at' => now()->subMonths(1)->addDays(5)
        ]);

        $this->assertTrue($phoneNumber->willRenewBeforeDays(8));
        $this->assertTrue($phoneNumber->willRenewBeforeDays(7));
        $this->assertTrue($phoneNumber->willRenewBeforeDays(6));
        $this->assertTrue($phoneNumber->willRenewBeforeDays(5));
        $this->assertFalse($phoneNumber->willRenewBeforeDays(4));
        $this->assertFalse($phoneNumber->willRenewBeforeDays(3));
        $this->assertFalse($phoneNumber->willRenewBeforeDays(2));
        $this->assertFalse($phoneNumber->willRenewBeforeDays(1));
        $this->assertFalse($phoneNumber->willRenewBeforeDays(0));
        $this->assertFalse($phoneNumber->willRenewBeforeDays(-1));
    }

    /**
     * Test checking calls for previous days
     * 
     * @group phone-numbers-unit
     */
    public function testCallsForPreviousDays()
    {
        $company = $this->createCompany();
        $config  = $this->createConfig($company);
        $phoneNumber = $this->createPhoneNumber($company, $config, [
            'purchased_at' => now()->subMonths(1)->addDays(5)
        ]);
        $contact         = $this->createContact($company);
        $recentCallCount = mt_rand(10, 20);

        for($i = 0; $i < $recentCallCount; $i++){
            $this->createCall($company, [
                'contact_id'      => $contact->id,
                'phone_number_id' => $phoneNumber->id,
                'created_at' => now()->subDays(2),
            ]);
        }

        for($i = 0; $i < 10; $i++){
            $this->createCall($company, [
                'contact_id'      => $contact->id,
                'phone_number_id' => $phoneNumber->id,
                'created_at' => now()->subDays(4),
            ]);
        }

        $this->assertEquals($phoneNumber->callsForPreviousDays(1), 0);
        $this->assertEquals($phoneNumber->callsForPreviousDays(2), $recentCallCount);
        $this->assertEquals($phoneNumber->callsForPreviousDays(3), $recentCallCount);
        $this->assertEquals($phoneNumber->callsForPreviousDays(4), $recentCallCount + 10);
    }
}
