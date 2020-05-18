<?php

namespace Tests\Unit;

use Faker\Factory as FakerFactory;
use \Tests\TestCase;

class SwapRuleTest extends TestCase
{
    use \Tests\CreatesAccount;

    /**
     * Test normalizing device type
     * 
     * @group swap-rules
     */
    public function testNormalizeDeviceType()
    {
        $company     = $this->createCompany();
        $config      = $this->createConfig($company); 
        $phoneNumber = $this->createPhoneNumber($company, $config);

        $this->assertTrue( $phoneNumber->normalizeDeviceType('desktop') == 'DESKTOP' );
        $this->assertTrue( $phoneNumber->normalizeDeviceType('tablet') == 'TABLET' );
        $this->assertTrue( $phoneNumber->normalizeDeviceType('smartphone') == 'MOBILE' );
        $this->assertTrue( $phoneNumber->normalizeDeviceType(str_random(10)) == 'OTHER' );
    }
    
    /**
     * Test normalizing browser type
     * 
     * @group swap-rules
     */
    public function testNormalizeBrowserType()
    {
        $company     = $this->createCompany();
        $config      = $this->createConfig($company); 
        $phoneNumber = $this->createPhoneNumber($company, $config);

        $this->assertTrue( $phoneNumber->normalizeBrowserType('Chrome Mobile') == 'CHROME' );
        $this->assertTrue( $phoneNumber->normalizeBrowserType('Firefox') == 'FIREFOX' );
        $this->assertTrue( $phoneNumber->normalizeBrowserType('IE Mobile') == 'INTERNET_EXPLORER');
        $this->assertTrue( $phoneNumber->normalizeBrowserType('Microsoft Edge') == 'EDGE' );
        $this->assertTrue( $phoneNumber->normalizeBrowserType('Mobile Safari') == 'SAFARI' );
        $this->assertTrue( $phoneNumber->normalizeBrowserType('Opera') == 'OPERA' );
        $this->assertTrue( $phoneNumber->normalizeBrowserType(str_random(10)) == 'OTHER' );
    }

     /**
     * Test rule functions
     * 
     * @group swap-rules
     */
    public function testRuleFunctions()
    {
        $company     = $this->createCompany();
        $config      = $this->createConfig($company); 
        $phoneNumber = $this->createPhoneNumber($company, $config);

       
        $faker  = FakerFactory::create();

        //  Test Direct
        $this->assertTrue($phoneNumber->isDirect(''));
        $this->assertTrue($phoneNumber->isDirect(null));
        $this->assertFalse($phoneNumber->isDirect($faker->url));

         //  Test Organic
        $this->assertTrue($phoneNumber->isOrganic('https://search.yahoo.com', $faker->url));
        $this->assertTrue($phoneNumber->isOrganic('https://yahoo.com', $faker->url));
        $this->assertTrue($phoneNumber->isOrganic('https://www.yahoo.com', $faker->url));

        $this->assertTrue($phoneNumber->isOrganic('https://www.google.com', $faker->url));
        $this->assertTrue($phoneNumber->isOrganic('https://google.com', $faker->url));

        $this->assertTrue($phoneNumber->isOrganic('https://bing.com', $faker->url));
        $this->assertTrue($phoneNumber->isOrganic('https://www.bing.com', $faker->url));

        $this->assertFalse($phoneNumber->isOrganic($faker->url, $faker->url));

        //  Test Paid
        $this->assertTrue($phoneNumber->isPaid($faker->url . '?utm_medium=cpc'));
        $this->assertTrue($phoneNumber->isPaid($faker->url . '?utm_medium=cpm'));
        $this->assertTrue($phoneNumber->isPaid($faker->url . '?utm_medium=PpC'));
        $this->assertTrue($phoneNumber->isPaid($faker->url . '?utm_medium=CPA&utm_source=' . str_random(3)));
        $this->assertFalse($phoneNumber->isPaid($faker->url));
   
        //  Test Search
        $this->assertTrue($phoneNumber->isPaid($faker->url . '?utm_medium=cpc'));
        $this->assertTrue($phoneNumber->isPaid($faker->url . '?utm_medium=cpm'));
        $this->assertTrue($phoneNumber->isPaid($faker->url . '?utm_medium=PpC'));
        $this->assertTrue($phoneNumber->isPaid($faker->url . '?utm_medium=CPA&utm_source=' . str_random(3)));
        $this->assertFalse($phoneNumber->isPaid($faker->url));

        //  Test Referral
        $this->assertTrue($phoneNumber->isReferral($faker->url, $faker->url));
        $this->assertTrue($phoneNumber->isReferral($faker->url, $faker->url . '?utm_medium=cpc'));
    }
}
