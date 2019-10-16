<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use \App\Models\Company\Campaign;
use \App\Models\Company\PhoneNumber;
use \App\Models\Company\PhoneNumberPool;
use \App\Models\Company\PhoneNumberPoolProvisionRule;

class PhoneNumberTest extends TestCase
{
    use \Tests\CreatesUser;

    /**
     * Test searching for a phone numbers
     * 
     * @group unit-phone-numbers
     */
    public function testSearchAvailablePhoneNumbers()
    {
        $provisionRule    = factory(PhoneNumberPoolProvisionRule::class)->make();

        $numbersAvailable = PhoneNumber::listAvailable($provisionRule->country, $provisionRule->area_code, 1, 5);

        $this->assertTrue(count($numbersAvailable) == 5);

        foreach( $numbersAvailable as $number ){
            $this->assertTrue(preg_match('/^(\+1' .$provisionRule->area_code . ')/', $number->phoneNumber) == true);
        }
    }


    /**
     * Test searching available phone numbers
     *
     * @group unit-phone-numbers
     */
    public function testPhoneNumberLookups()
    {
        $magicNumbers = config('services.twilio.magic_numbers');

        $user = $this->createUser();

        //  Test searching a toll free number with no area code
        $numbers = PhoneNumber::lookup(null, false, [], 2);
        $this->assertTrue(count($numbers) == 2);
        foreach($numbers as $number){
            $this->assertTrue($number['toll_free'] == true);
            $this->assertTrue(stripos($number['phone'], '+18') === 0);
        }

        //  Test searching a local phone number without an area code
        $numbers = PhoneNumber::lookup(null, true, [], 2);
        $this->assertTrue(count($numbers) == 2);
        foreach($numbers as $number){
            $this->assertTrue($number['local'] == true);
        }

        //  Now, with an area code ... 
        $numbers = PhoneNumber::lookup(null, true, [], 2);
        $this->assertTrue(count($numbers) == 2);
        foreach($numbers as $number){
            $this->assertTrue($number['local'] == true);
        }

        //  Now test purchasing an available phone number
        $numberData = PhoneNumber::purchase($magicNumbers['available']);
        $this->assertTrue($numberData != null);

        //  Now try deleting the number
        $number = $this->createPhoneNumber([
            'company_id'   => $this->company->id,
            'created_by'   => $user->id,
            'external_id'  => \str_random(40)
        ]);
        $this->assertTrue(PhoneNumber::find($number->id) != null);
        $number->release();
        $this->assertTrue(PhoneNumber::find($number->id) == null);

        //  Now an unavailable one ...
        $this->expectException(\Twilio\Exceptions\RestException::class);
        PhoneNumber::purchase($magicNumbers['unavailable']);

        //  Finally, an invalid one ...
        $this->expectException(\Twilio\Exceptions\RestException::class);
        PhoneNumber::purchase($magicNumbers['invalid']);
    }

    /**
     * Test cleaning phone numbers
     *
     * @group unit-phone-numbers
     */
    public function testPhoneNumberCanClean()
    {
        $countryCode = '1';
        $number      = '7778889999';
        $phone = '+' . $countryCode . $number;

        $this->assertTrue(PhoneNumber::countryCode($phone) == $countryCode);
        $this->assertTrue(PhoneNumber::number($phone) == $number);
    }

    /**
     * Test checking if a phone number is in use
     *
     * @group phone-numbers-
     */
    public function testPhoneNumberIsUse()
    {
        $user = $this->createUser();

        $campaign    = factory(Campaign::class)->create([
            'company_id'   => $this->company->id,
            'created_by'   => $user->id,
            'activated_at' => date('Y-m-d H:i:s', strtotime('now -10 days'))
        ]);

        $phone = $this->createPhoneNumber([
            'company_id'   => $this->company->id,
            'created_by'   => $user->id,
            'external_id'  => \str_random(40)
        ]);

        $this->assertTrue($phone->isInUse() === false);

        $phone->campaign_id = $campaign->id;
        $phone->save();

        $this->assertTrue($phone->isInUse() === true);

        $phone->campaign_id = null;
        $phone->save();

        $this->assertTrue($phone->isInUse() === false);
    }

    /**
     * Test checking if a phone number is in use
     *
     * @group unit-phone-numbers
     */
    public function testPhoneNumberPoolIsUse()
    {
        $user = $this->createUser();

        $pool = $this->createPhoneNumberPool([
            'company_id' => $this->company->id,
            'created_by' => $user->id
        ]);

        $phone = $this->createPhoneNumber([
            'company_id'    => $this->company->id,
            'created_by'    => $user->id,
            'external_id'   => str_random(40)
        ]);

        $phone2 = $this->createPhoneNumber([
            'company_id'    => $this->company->id,
            'created_by'    => $user->id,
            'external_id'   => str_random(40)
        ]);

        $campaign    = $this->createCampaign([
            'company_id'   => $this->company->id,
            'created_by'   => $user->id,
            'activated_at' => date('Y-m-d H:i:s', strtotime('now -10 days'))
        ]);

        //  Make sure they know they're not in use
        $this->assertTrue($pool->isInUse() === false);
        $this->assertTrue($phone->isInUse() === false);
        $this->assertTrue($phone2->isInUse() === false);

        //  Check as a group
        $numberArr = [$phone->id, $phone2->id];
        $numbersInUse = PhoneNumber::numbersInUse($numberArr);
        $this->assertTrue(count($numbersInUse) == 0);

        //  Set pool's campaign and make sure they are in use now
        $pool->campaign_id = $campaign->id;
        $pool->save();

        $phone->phone_number_pool_id = $pool->id;
        $phone->save();

        $phone2->phone_number_pool_id = $pool->id;
        $phone2->save();

        $this->assertTrue($pool->isInUse() === true);
        $this->assertTrue($phone->isInUse() === true);
        $this->assertTrue($phone2->isInUse() === true);

        //  Check as a group
        $numbersInUse = PhoneNumber::numbersInUse($numberArr);
        $this->assertTrue(count($numbersInUse) == 2);
        $this->assertTrue(in_array($phone->id, $numbersInUse) && in_array($phone2->id, $numbersInUse) );
        
        //  Remove second number from pool and check again
        $phone2->phone_number_pool_id = null;
        $phone2->save();

        $this->assertTrue($phone2->isInUse() === false);

        $numbersInUse = PhoneNumber::numbersInUse($numberArr);

        $this->assertTrue(count($numbersInUse) == 1);
        $this->assertTrue(in_array($phone->id, $numbersInUse));
    }

}
