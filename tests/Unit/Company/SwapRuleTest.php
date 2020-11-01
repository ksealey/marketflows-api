<?php

namespace Tests\Unit;

use Faker\Factory as FakerFactory;
use \Tests\TestCase;
use App\Services\SessionService;
use App;

class SwapRuleTest extends TestCase
{
    use \Tests\CreatesAccount;
    
    /**
     * Test normalizing browser type
     * 
     * @group swap-rules
     */
    public function testNormalizeBrowserType()
    {
        $sessionService = App::make(SessionService::class);

        $this->assertTrue( $sessionService->normalizeBrowserType('Chrome Mobile') == 'CHROME' );
        $this->assertTrue( $sessionService->normalizeBrowserType('Firefox') == 'FIREFOX' );
        $this->assertTrue( $sessionService->normalizeBrowserType('IE Mobile') == 'INTERNET_EXPLORER');
        $this->assertTrue( $sessionService->normalizeBrowserType('Microsoft Edge') == 'EDGE' );
        $this->assertTrue( $sessionService->normalizeBrowserType('Mobile Safari') == 'SAFARI' );
        $this->assertTrue( $sessionService->normalizeBrowserType('Opera') == 'OPERA' );
        $this->assertTrue( $sessionService->normalizeBrowserType(str_random(10)) == 'OTHER' );
    }

     /**
     * Test rule functions
     * 
     * @group swap-rules
     */
    public function testRuleFunctions()
    {
        $company        = $this->createCompany();
        $sessionService = App::make(SessionService::class);
        $faker          = FakerFactory::create();

        //  Test Direct
        $this->assertTrue($sessionService->isDirect('', $faker->url));
        $this->assertTrue($sessionService->isDirect(null, $faker->url));
        $this->assertTrue($sessionService->isDirect('https://something.com/', 'https://something.com'));
        $this->assertFalse($sessionService->isDirect($faker->url, $faker->url));
        

         //  Test Organic
        $this->assertTrue($sessionService->isOrganic('https://search.yahoo.com', $faker->url, $company->medium_param));
        $this->assertTrue($sessionService->isOrganic('https://yahoo.com', $faker->url, $company->medium_param));
        $this->assertTrue($sessionService->isOrganic('https://www.yahoo.com', $faker->url, $company->medium_param));

        $this->assertTrue($sessionService->isOrganic('https://www.google.com', $faker->url, $company->medium_param));
        $this->assertTrue($sessionService->isOrganic('https://google.com', $faker->url, $company->medium_param));

        $this->assertTrue($sessionService->isOrganic('https://bing.com', $faker->url, $company->medium_param));
        $this->assertTrue($sessionService->isOrganic('https://www.bing.com', $faker->url, $company->medium_param));

        $this->assertFalse($sessionService->isOrganic($faker->url, $faker->url, $company->medium_param));

        //  Test Paid
        $this->assertTrue($sessionService->isPaid($faker->url . '?utm_medium=cpc', $company->medium_param));
        $this->assertTrue($sessionService->isPaid($faker->url . '?utm_medium=cpm', $company->medium_param));
        $this->assertTrue($sessionService->isPaid($faker->url . '?utm_medium=PpC', $company->medium_param));
        $this->assertTrue($sessionService->isPaid($faker->url . '?utm_medium=CPA&utm_source=' . str_random(3), $company->medium_param));
        $this->assertFalse($sessionService->isPaid($faker->url, $company->medium_param));
   
        //  Test Search
        $this->assertTrue($sessionService->isSearch('https://google.com/?utm_medium=cpc'));
        $this->assertTrue($sessionService->isSearch('https://www.google.com?utm_medium=cpc'));
        $this->assertTrue($sessionService->isSearch('https://yahoo.com?utm_medium=cpc'));
        $this->assertTrue($sessionService->isSearch('https://www.yahoo.com?utm_medium=cpc'));
        $this->assertTrue($sessionService->isSearch('https://search.yahoo.com?utm_medium=cpc'));
        $this->assertTrue($sessionService->isSearch('https://bing.com?utm_medium=cpc'));
        $this->assertTrue($sessionService->isSearch('https://www.bing.com?utm_medium=cpc'));
        $this->assertTrue($sessionService->isSearch('https://duckduckgo.com?utm_medium=cpc'));
        $this->assertTrue($sessionService->isSearch('https://www.duckduckgo.com?utm_medium=cpc'));
        $this->assertTrue($sessionService->isSearch('https://yandex.com?utm_medium=cpc'));
        $this->assertTrue($sessionService->isSearch('https://www.yandex.com?utm_medium=cpc'));
        $this->assertFalse($sessionService->isSearch('https://twitter.com?utm_medium=cpc'));
        $this->assertFalse($sessionService->isSearch('https://facebook.com?utm_medium=cpc'));
        $this->assertFalse($sessionService->isSearch('https://instagram.com?utm_medium=cpc'));
        $this->assertFalse($sessionService->isSearch('https://linkedin.com?utm_medium=cpc'));
        $this->assertFalse($sessionService->isSearch('https://www.freesamples.com?utm_term=123'));

        //  Test paid search
        $this->assertTrue($sessionService->isPaid($faker->url . '?utm_medium=cpc', $company->medium_param));
        $this->assertTrue($sessionService->isPaid($faker->url . '?utm_medium=cpp', $company->medium_param));
        
        $paidSearchRef   = 'https://google.com';
        $paidSearchEntry = $faker->url .'?utm_medium=cpc&utm_source=google';
        $this->assertTrue(
            $sessionService->isPaid($paidSearchEntry, $company->medium_param) && 
            $sessionService->isSearch($paidSearchRef) &&
            !$sessionService->isOrganic($paidSearchRef, $paidSearchEntry, $company->medium_param)
        );
       
        //  Test Referral
        $this->assertTrue($sessionService->isReferral($faker->url, $faker->url));
        $this->assertTrue($sessionService->isReferral($faker->url, $faker->url . '?utm_medium=cpc'));
    }
}
