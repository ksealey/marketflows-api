<?php

namespace Tests\Unit\Company;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use \App\Models\Company\PhoneNumber;
use \App\Models\Company\PhoneNumberPool;
use \App\Models\Company\PhoneNumberPoolProvisionRule;

class PhoneNumberPoolTest extends TestCase
{
    use \Tests\CreatesUser;

    /**
     * Test provisioning a phone number
     * 
     * @group unit-phone-number-pools
     */
    public function testProvisionPhoneNumber()
    {
        $pool = $this->createPhoneNumberPool();
        
        $provisionRule = factory(PhoneNumberPoolProvisionRule::class)->create([
            'phone_number_pool_id'  => $pool->id,
            'created_by'            => $this->user->id,
            'priority'              => 1,
        ]);
        
        $provisionPhone = config('services.twilio.magic_numbers.available');
        $phoneNumber    = $pool->provisionPhone($provisionRule, $provisionPhone);

        $this->assertTrue($phoneNumber != null);
        $this->assertTrue($phoneNumber->e164Format() == $provisionPhone);
        $this->assertTrue($phoneNumber->company_id == $pool->company_id);
        $this->assertTrue($phoneNumber->phone_number_pool_id == $pool->id);
        $this->assertTrue($phoneNumber->auto_provisioned_by == $pool->id);
        $this->assertTrue($phoneNumber->phone_number_pool_provision_rule_id == $provisionRule->id);
        $this->assertTrue($phoneNumber->phone_number_config_id == $pool->phone_number_config_id);
        $this->assertTrue($phoneNumber->voice);
    }

    /**
     * Test auto-provisioning a phone number does not happen disabled
     * 
     * @group unit-phone-number-pools-
     */
    public function testAutoProvisionPhoneNumberFailsWhenDisabled()
    {
        $pool = $this->createPhoneNumberPool([
            'auto_provision_enabled_at' => null
        ]);

        $provisionPhone = config('services.twilio.magic_numbers.available');

        $provisionRule = factory(PhoneNumberPoolProvisionRule::class)->create([
            'phone_number_pool_id'  => $pool->id,
            'created_by'            => $this->user->id,
            'priority'              => 0,
        ]);

        $phoneNumber = $pool->autoProvisionPhone($provisionPhone);

        $this->assertTrue($phoneNumber == null);
    }

    /**
     * Test auto-provisioning a phone number does not happen when max_allowed is reached
     * 
     * @group unit-phone-number-pools-
     */
    public function testAutoProvisionPhoneNumberFailsWhenMaxReached()
    {
        $provisionPhone = config('services.twilio.magic_numbers.available');

        $pool = $this->createPhoneNumberPool([
            'auto_provision_max_allowed' => 3
        ]);

        $provisionRule = factory(PhoneNumberPoolProvisionRule::class)->create([
            'phone_number_pool_id'  => $pool->id,
            'created_by'            => $this->user->id,
            'priority'              => 0,
        ]);


        //  Test that it works with a single number
        $firstPhoneNumber = $this->createPhoneNumber([
            'phone_number_pool_id' => $pool->id
        ]);

        $secondPhoneNumber = $pool->autoProvisionPhone($provisionPhone);
        $this->assertTrue($secondPhoneNumber != null);

        //  Add add a third number and make sure it fails
        $thirdPhoneNumber = $this->createPhoneNumber([
            'phone_number_pool_id' => $pool->id
        ]);
        $phoneNumber = $pool->autoProvisionPhone($provisionPhone);
        $this->assertTrue($phoneNumber == null);
        
        //  Remove the first phone number and make sure it works again
        $firstPhoneNumber->delete();
        $phoneNumber = $pool->autoProvisionPhone($provisionPhone);
        $this->assertTrue($phoneNumber != null);

       
        // Remove that number, so there is room for one more, 
        // But restrict at the company limit and make sure it fails
        $phoneNumber->delete();
        $pool->company->phone_number_max_allowed = 2;
        $pool->company->save();
        $this->assertTrue(
            PhoneNumber::where('phone_number_pool_id', $pool->id)->count() < $pool->auto_provision_max_allowed
        );
        $phoneNumber = $pool->autoProvisionPhone($provisionPhone);
        $this->assertTrue($phoneNumber == null);
    }
}
