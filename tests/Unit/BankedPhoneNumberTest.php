<?php

namespace Tests\Unit;

use \Tests\TestCase;
use \App\Models\BankedPhoneNumber;
use \App\Helpers\PhoneNumberManager;
use Artisan;

class BankedPhoneNumberTest extends TestCase
{
    use \Tests\CreatesAccount;
    /**
     * Test banked phone numbers are deleted as expected
     * 
     * @group banked-phone-numbers
     */
    public function testBankedPhoneNumbersAreDeletedAsExpected()
    {
        BankedPhoneNumber::where('id', '>', 0)->forceDelete();

        factory(BankedPhoneNumber::class, 10)->create([
            'released_by_account_id' => $this->account->id,
            'release_by' => now()->addDays(1)
        ]);

        $this->mock(PhoneNumberManager::class, function ($mock){
            $mock->shouldReceive('releaseNumber')
                ->times(10);
        });

        Artisan::call('banked-phone-numbers:release-to-be-renewed');
    }

    /**
     * Test banked phone numbers are not deleted when the will expire in 3 days
     * 
     * @group banked-phone-numbers
     */
    public function testBankedPhoneNumbersWillNotBeDeletedIfWithinThreshold()
    {
        BankedPhoneNumber::where('id', '>', 0)->forceDelete();

        factory(BankedPhoneNumber::class, 10)->create([
            'released_by_account_id' => $this->account->id,
            'release_by' => now()->addDays(3)
        ]);

        $this->mock(PhoneNumberManager::class, function ($mock){
            $mock->shouldNotReceive('releaseNumber');
        });

        Artisan::call('banked-phone-numbers:release-to-be-renewed');
    }
}
