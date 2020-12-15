<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;

class PhoneNumberConfigTest extends TestCase
{
    use \Tests\CreatesAccount, WithFaker;

    /**
     * Test formatting forwarding numbers
     * 
     * @group phone-number-configs
     */
    public function testPhoneNumberConfigFormatsForwardingNumersProperly()
    {
        $company = $this->createCompany();
        $config  = $this->createConfig($company, [
            'forward_to_number' => '8889990000'
        ]);

        $this->assertEquals($config->forwardToPhoneNumber('US'), '+18889990000');
        
        $config->forward_to_number = '18889990000';
        $this->assertEquals($config->forwardToPhoneNumber('US'), '+18889990000');

        $config->forward_to_number = '428889990000';
        $this->assertEquals($config->forwardToPhoneNumber('US'), '+428889990000');
        
    }

    /**
     * Test swapping variables in messages
     * 
     * @group phone-number-configs
     */
    public function testPhoneNumberSwapsMessageVariables()
    {
        $company = $this->createCompany();
        $config  = $this->createConfig($company, [
            'forward_to_number'         => '8889990000',
            'keypress_directions_message'=> 'Hello ${First_Name} ${Last_NAME}. Thank you for calling ${ComPany_Name}. ${Undefined_Variable}'
        ]);

        $faker       = $this->faker();
        $firstName   = $faker->firstName;
        $lastName    = $faker->lastName;
   
        $variables = [
            'first_name'    => $firstName,
            'last_name'     => $lastName,
        ];

        $this->assertEquals(
            $config->message('keypress_directions_message',$variables),
            "Hello {$firstName} {$lastName}. Thank you for calling {$company->name}. \${Undefined_Variable}"
        );
    }
}
