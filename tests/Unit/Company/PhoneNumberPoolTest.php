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
     * @group unit-phone-number-pools
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
     * @group unit-phone-number-pools
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

    /**
     * Test assigning a phone number without a provision rule rotates
     * 
     * @group unit-phone-number-pools
     */
    public function testAssignPhoneNumberRotates()
    {
        $pool = $this->createPhoneNumberPool([
            'auto_provision_enabled_at' => null
        ]);

        $provisionRule = factory(PhoneNumberPoolProvisionRule::class)->create([
            'phone_number_pool_id'  => $pool->id,
            'created_by'            => $this->user->id,
            'priority'              => 0,
        ]);
        
        $phoneNumber1 = $this->createPhoneNumber([
            'phone_number_pool_id' => $pool->id,
            'last_assigned_at'     => null,
            'assigned_at'          => null
        ]);

        $phoneNumber2 = $this->createPhoneNumber([
            'phone_number_pool_id' => $pool->id,
            'last_assigned_at'     => null,
            'assigned_at'          => null
        ]);

        $phoneNumber3 = $this->createPhoneNumber([
            'phone_number_pool_id' => $pool->id,
            'last_assigned_at'     => null,
            'assigned_at'          => null
        ]);

        //  Make sure the phone numbers are rotated properly
        $assignedPhone = $pool->assignPhoneNumber();
        $this->assertTrue($assignedPhone->id === $phoneNumber1->id);

        $assignedPhone = $pool->assignPhoneNumber();
        $this->assertTrue($assignedPhone->id === $phoneNumber2->id);

        $assignedPhone = $pool->assignPhoneNumber();
        $this->assertTrue($assignedPhone->id === $phoneNumber3->id);

        $assignedPhone = $pool->assignPhoneNumber();
        $this->assertTrue($assignedPhone->id === $phoneNumber1->id);

        $assignedPhone = $pool->assignPhoneNumber();
        $this->assertTrue($assignedPhone->id === $phoneNumber2->id);

        $assignedPhone = $pool->assignPhoneNumber();
        $this->assertTrue($assignedPhone->id === $phoneNumber3->id);
    }

    /**
     * Test assigning a phone number with a preferred number
     * 
     * @group unit-phone-number-pools
     */
    public function testAssignPhoneWithPreferredNumber()
    {
        $pool = $this->createPhoneNumberPool([
            'auto_provision_enabled_at' => null
        ]);

        $provisionRule = factory(PhoneNumberPoolProvisionRule::class)->create([
            'phone_number_pool_id'  => $pool->id,
            'created_by'            => $this->user->id,
            'priority'              => 0,
        ]);
        
        $phoneNumber1 = $this->createPhoneNumber([
            'phone_number_pool_id' => $pool->id,
            'last_assigned_at'     => null,
            'assigned_at'          => null
        ]);

        $phoneNumber2 = $this->createPhoneNumber([
            'phone_number_pool_id' => $pool->id,
            'last_assigned_at'     => null,
            'assigned_at'          => null
        ]);

        $phoneNumber3 = $this->createPhoneNumber([
            'phone_number_pool_id' => $pool->id,
            'last_assigned_at'     => null,
            'assigned_at'          => null
        ]);

        $assignedPhone = $pool->assignPhoneNumber($phoneNumber2->uuid);

        $this->assertTrue($assignedPhone->id == $phoneNumber2->id);
    }

    /**
     * Test assigning a phone number with a preferred number that is unavailable
     * 
     * @group unit-phone-number-pools
     */
    public function testAssignPhoneWithUnavailablePreferredNumber()
    {
        $now = new \DateTime();

        $pool = $this->createPhoneNumberPool([
            'auto_provision_enabled_at' => null
        ]);

        $provisionRule = factory(PhoneNumberPoolProvisionRule::class)->create([
            'phone_number_pool_id'  => $pool->id,
            'created_by'            => $this->user->id,
            'priority'              => 0,
        ]);
        
        $phoneNumber1 = $this->createPhoneNumber([
            'phone_number_pool_id' => $pool->id,
            'last_assigned_at'     => null,
            'assigned_at'          => null
        ]);

        $phoneNumber2 = $this->createPhoneNumber([
            'phone_number_pool_id' => $pool->id,
            'last_assigned_at'     => $now->format('Y-m-d H:i:s.u'),
            'assigned_at'          => $now->format('Y-m-d H:i:s.u')
        ]);

        $phoneNumber3 = $this->createPhoneNumber([
            'phone_number_pool_id' => $pool->id,
            'last_assigned_at'     => null,
            'assigned_at'          => null
        ]);

        //  Make sure we grab the first number
        $assignedPhone = $pool->assignPhoneNumber($phoneNumber2->uuid);
        $this->assertTrue($assignedPhone->id == $phoneNumber1->id);

        //  Make sure we grab the number after it
        $assignedPhone = $pool->assignPhoneNumber($phoneNumber2->uuid);
        $this->assertTrue($assignedPhone->id == $phoneNumber3->id);

        //  Make sure when all numbers are used up, we always get the preferred number
        $assignedPhone = $pool->assignPhoneNumber($phoneNumber2->uuid);
        $this->assertTrue($assignedPhone->id == $phoneNumber2->id);

        $assignedPhone = $pool->assignPhoneNumber($phoneNumber2->uuid);
        $this->assertTrue($assignedPhone->id == $phoneNumber2->id);

        $assignedPhone = $pool->assignPhoneNumber($phoneNumber2->uuid);
        $this->assertTrue($assignedPhone->id == $phoneNumber2->id);

        //  Make sure rotate happens as normal
        $assignedPhone = $pool->assignPhoneNumber();
        $this->assertTrue($assignedPhone->id == $phoneNumber1->id);
        $assignedPhone = $pool->assignPhoneNumber();
        $this->assertTrue($assignedPhone->id == $phoneNumber3->id);
        $assignedPhone = $pool->assignPhoneNumber();
        $this->assertTrue($assignedPhone->id == $phoneNumber2->id);
        $assignedPhone = $pool->assignPhoneNumber();
        $this->assertTrue($assignedPhone->id == $phoneNumber1->id);
    }

    /**
     * Test assigning a phone number when there are provisioning rules
     * 
     * @group unit-phone-number-pools-
     */
    public function testAssignPhoneWithProvisioningEnabled()
    {
        $provisionPhone = config('services.twilio.magic_numbers.available');

        $now = new \DateTime();

        $pool = $this->createPhoneNumberPool([
            'auto_provision_max_allowed' => 3
        ]);

        $provisionRule = factory(PhoneNumberPoolProvisionRule::class)->create([
            'phone_number_pool_id'  => $pool->id,
            'created_by'            => $this->user->id,
            'priority'              => 0,
        ]);
        
        $phoneNumber1 = $this->createPhoneNumber([
            'phone_number_pool_id' => $pool->id,
            'last_assigned_at'     => null,
            'assigned_at'          => null
        ]);

        $phoneNumber2 = $this->createPhoneNumber([
            'phone_number_pool_id' => $pool->id,
            'last_assigned_at'     => null,
            'assigned_at'          => null
        ]);

        //  Make sure the existing numbers are rotated as needed
        $assignedPhone = $pool->assignPhoneNumber();
        $this->assertTrue($assignedPhone->id == $phoneNumber1->id);
        $assignedPhone = $pool->assignPhoneNumber();
        $this->assertTrue($assignedPhone->id == $phoneNumber2->id);

        //  Make sure that we get a new phone number
        $phoneNumber3 = $pool->assignPhoneNumber(null, $provisionPhone);
        $this->assertTrue($phoneNumber3->id != $phoneNumber1->id && $phoneNumber3->id != $phoneNumber2->id);
        $this->assertTrue($phoneNumber3->phone_number_pool_provision_rule_id == $provisionRule->id);
        
        //  Make sure that the next time we try to get a phone number, it returns an existing one from totation
        $assignedPhone = $pool->assignPhoneNumber(null, $provisionPhone);
        $this->assertTrue($assignedPhone->id == $phoneNumber1->id);
        
        $assignedPhone = $pool->assignPhoneNumber(null, $provisionPhone);
        $this->assertTrue($assignedPhone->id == $phoneNumber2->id);

        $assignedPhone = $pool->assignPhoneNumber(null, $provisionPhone);
        $this->assertTrue($assignedPhone->id == $phoneNumber3->id);

        $assignedPhone = $pool->assignPhoneNumber(null, $provisionPhone);
        $this->assertTrue($assignedPhone->id == $phoneNumber1->id);
        
        $assignedPhone = $pool->assignPhoneNumber(null, $provisionPhone);
        $this->assertTrue($assignedPhone->id == $phoneNumber2->id);

        $assignedPhone = $pool->assignPhoneNumber(null, $provisionPhone);
        $this->assertTrue($assignedPhone->id == $phoneNumber3->id);
    }
}
