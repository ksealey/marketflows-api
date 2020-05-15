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
    public function testWillRenewInDays()
    {
        $company = $this->createCompany();
        $config  = $this->createConfig($company);
        $phoneNumber = $this->createPhoneNumber($company, $config, [
            'purchased_at' => now()->subMonths(1)->addDays(5)
        ]);

        $this->assertTrue($phoneNumber->willRenewInDays(8));
        $this->assertTrue($phoneNumber->willRenewInDays(7));
        $this->assertTrue($phoneNumber->willRenewInDays(6));
        $this->assertTrue($phoneNumber->willRenewInDays(5));
        $this->assertFalse($phoneNumber->willRenewInDays(4));
        $this->assertFalse($phoneNumber->willRenewInDays(3));
        $this->assertFalse($phoneNumber->willRenewInDays(2));
        $this->assertFalse($phoneNumber->willRenewInDays(1));
        $this->assertFalse($phoneNumber->willRenewInDays(0));
        $this->assertFalse($phoneNumber->willRenewInDays(-1));
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

        $recentCallCount = mt_rand(10, 20);

        factory(Call::class, $recentCallCount)->create([
            'account_id'      => $company->account_id,
            'company_id'      => $company->id,
            'phone_number_id' => $phoneNumber->id,
            'created_at'      => now()->subDays(2),
        ]);

        factory(Call::class, 10)->create([
            'account_id'      => $company->account_id,
            'company_id'      => $company->id,
            'phone_number_id' => $phoneNumber->id,
            'created_at'      => now()->subDays(4),
        ]);

        $this->assertEquals($phoneNumber->callsForPreviousDays(1), 0);
        $this->assertEquals($phoneNumber->callsForPreviousDays(2), $recentCallCount);
        $this->assertEquals($phoneNumber->callsForPreviousDays(3), $recentCallCount);
        $this->assertEquals($phoneNumber->callsForPreviousDays(4), $recentCallCount + 10);
    }
}
