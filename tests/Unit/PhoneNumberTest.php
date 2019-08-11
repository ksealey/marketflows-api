<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use \App\Models\PhoneNumber;

class PhoneNumberTest extends TestCase
{
    use \Tests\CreatesUser;

    /**
     * Test searching available phone numbers
     *
     * @group phone-numbers
     */
    public function testPhoneNumberLookups()
    {
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
        PhoneNumber::testing();

        $numberData = PhoneNumber::purchase('15005550006');
        $this->assertTrue($numberData != null);

        //  Now try deleting the number
        $number = factory(PhoneNumber::class)->create([
            'company_id' => $user->company_id,
            'created_by' => $user->id,
            'twilio_id'  => \str_random(40)
        ]);
        $this->assertTrue(PhoneNumber::find($number->id) != null);
        $number->release();
        $this->assertTrue(PhoneNumber::find($number->id) == null);

        //  Now an unavailable one ...
        $this->expectException(\Twilio\Exceptions\RestException::class);
        PhoneNumber::purchase('15005550000');

        //  Finally, an invalid one ...
        $this->expectException(\Twilio\Exceptions\RestException::class);
        PhoneNumber::purchase('15005550001');
    }

     /**
     * Test cleaning phone numbers
     *
     * @group phone-numbers
     */
    public function testPhoneNumberCanClean()
    {
        $countryCode = '1';
        $number      = '7778889999';
        $phone = '+' . $countryCode . $number;

        $this->assertTrue(PhoneNumber::countryCode($phone) == $countryCode);
        $this->assertTrue(PhoneNumber::phone($phone) == $number);
    }

}
