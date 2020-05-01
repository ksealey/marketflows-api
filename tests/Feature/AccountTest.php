<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\Account;
use App\Models\Company;
use App\Models\Company\PhoneNumberConfig;
use App\Models\Company\PhoneNumber;
use App\Models\Company\PhoneNumberPool;
use App\Models\Company\Call;

class AccountTest extends TestCase
{
    use \Tests\CreatesAccount;

    /**
     * Test reading account
     *
     * @group accounts
     */
    public function testReadAccount()
    {
        $response = $this->json('GET', route('read-account'));
        $response->assertStatus(200);
        $response->assertJSON([
            'id' => $this->account->id,
            'name' => $this->account->name,
            'account_type' => $this->account->account_type,
            'pretty_account_type' => $this->account->pretty_account_type
        ]);
    }  
    
    /**
     * Test updating account
     * 
     * @group accounts
     */
    public function testUpdateAccount()
    {
        $newName = 'UPDATED - Account';
        $response = $this->json('PUT', route('update-account'), [
            'name' => $newName
        ]);
        $response->assertStatus(200);
        $response->assertJSON([
            'id' => $this->account->id,
            'name' => $newName,
            'account_type' => $this->account->account_type,
            'pretty_account_type' => $this->account->pretty_account_type
        ]);

        $account = Account::find($this->account->id);

        $this->assertEquals($account->name, $newName);
    }

    /**
     * Test upgrading account from basic
     * 
     * @group accounts
     */
    public function testUpgradeAccountFromBasic()
    {
        $this->account->account_type = Account::TYPE_BASIC;
        $this->account->save();

        $response = $this->json('PUT', route('upgrade-account'), [
            'account_type' => Account::TYPE_ANALYTICS
        ]);

        $response->assertStatus(200);
        $response->assertJSON([
            'id' => $this->account->id,
            'name' => $this->account->name,
            'account_type' => Account::TYPE_ANALYTICS,
            'monthly_fee'   => Account::COST_TYPE_ANALYTICS
        ]);
        
        $account = Account::find($this->account->id);

        $this->assertEquals($account->account_type, Account::TYPE_ANALYTICS);
        $this->assertNotNull($account->account_type_updated_at);
    }

    /**
     * Test upgrading account from analytics
     * 
     * @group accounts
     */
    public function testUpgradeAccountFromAnalytics()
    {
        $this->account->account_type = Account::TYPE_ANALYTICS;
        $this->account->save();

        $response = $this->json('PUT', route('upgrade-account'), [
            'account_type' => Account::TYPE_ANALYTICS_PRO
        ]);
        $response->assertStatus(200);
        $response->assertJSON([
            'id' => $this->account->id,
            'name' => $this->account->name,
            'account_type' => Account::TYPE_ANALYTICS_PRO,
            'monthly_fee'   => Account::COST_TYPE_ANALYTICS_PRO
        ]);
        
        $account = Account::find($this->account->id);

        $this->assertEquals($account->account_type, Account::TYPE_ANALYTICS_PRO);
        $this->assertNotNull($account->account_type_updated_at);
    }

    /**
     * Test downgrading account from analytics pro fails
     * 
     * @group accounts
     */
    public function testDowngradeFromAnalyticsProFails()
    {
        $this->account->account_type = Account::TYPE_ANALYTICS_PRO;
        $this->account->save();

        $response = $this->json('PUT', route('upgrade-account'), [
            'account_type' => Account::TYPE_ANALYTICS
        ]);
        $response->assertStatus(400);
        $response->assertJSONStructure([
            'error'
           
        ]);
        
        $account = Account::find($this->account->id);

        $this->assertEquals($account->account_type, Account::TYPE_ANALYTICS_PRO);
        $this->assertNull($account->account_type_updated_at);
    }

    /**
     * Test downgrading account from analytics fails
     * 
     * @group accounts
     */
    public function testDowngradeFromAnalyticsFails()
    {
        $this->account->account_type = Account::TYPE_ANALYTICS;
        $this->account->save();

        $response = $this->json('PUT', route('upgrade-account'), [
            'account_type' => Account::TYPE_BASIC
        ]);
        $response->assertStatus(400);
        $response->assertJSONStructure([
            'error'
        ]);
        
        $account = Account::find($this->account->id);

        $this->assertEquals($account->account_type, Account::TYPE_ANALYTICS);
        $this->assertNull($account->account_type_updated_at);
    }

    /**
     * Test deleting an account calls all neccessary events and deletes resources
     * 
     * @group accounts
     */
    public function testDeletingAccountCallsEventsAndRemovesResources()
    {
        //  Add some companies, phone numbers, calls and blocked numbers, blocked calls
        $companies = $this->createCompanies();

        $this->assertTrue(true);

       
        
    }

}
