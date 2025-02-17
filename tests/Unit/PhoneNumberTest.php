<?php

namespace Tests\Unit;

use Tests\TestCase;
use \App\Models\Company\PhoneNumber;
use \App\Models\Company\Call;

class PhoneNumberTest extends TestCase
{
    use \Tests\CreatesAccount;

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
                'phone_number_name' => $phoneNumber->name,
                'created_at' => now()->subDays(2),
            ]);
        }

        for($i = 0; $i < 10; $i++){
            $this->createCall($company, [
                'contact_id'      => $contact->id,
                'phone_number_id' => $phoneNumber->id,
                'phone_number_name' => $phoneNumber->name,
                'created_at' => now()->subDays(4),
            ]);
        }

        $this->assertEquals($phoneNumber->callsForPreviousDays(1), 0);
        $this->assertEquals($phoneNumber->callsForPreviousDays(2), $recentCallCount);
        $this->assertEquals($phoneNumber->callsForPreviousDays(3), $recentCallCount);
        $this->assertEquals($phoneNumber->callsForPreviousDays(4), $recentCallCount + 10);
    }
}
