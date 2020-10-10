<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\Account;
use App\Models\Company;
use App\Models\Company\PhoneNumber;
use App\Models\Company\Call;
use Queue;

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
            'name' => $this->account->name
        ]);
    }  
    
    /**
     * Test updating account
     * 
     * @group accounts
     */
    public function testUpdateAccount()
    {
        $account = factory(Account::class)->make();
        $response = $this->json('PUT', route('update-account'), [
            'name'          => $account->name,
            'tts_language'  => $account->tts_language,
            'tts_voice'     => $account->tts_voice
        ]);
        $response->assertStatus(200);
        $response->assertJSON([
            'id'            => $this->account->id,
            'name'          => $account->name,
            'tts_language'  => $account->tts_language,
            'tts_voice'     => $account->tts_voice
        ]);

        $account = Account::find($this->account->id);

        $this->assertDatabaseHas('accounts', [
            'id' => $this->account->id,
            'name'          => $account->name,
            'tts_language'  => $account->tts_language,
            'tts_voice'     => $account->tts_voice
        ]);
    }

    /**
     * Test closing account fails with past due statement
     * 
     * @group accounts
     */
    public function testClosingAccountFailsForPastDueStatement()
    {
        $statement = $this->createBillableStatement([
            'billing_id'               => $this->billing->id,
            'billing_period_starts_at' => now()->subDays(30)->startOfDay(),
            'billing_period_ends_at'   => now()->endOfDay()
        ]);

        $response = $this->json('DELETE', route('delete-account', [
            'confirm_close' => 1
        ]));
        
        $response->assertStatus(400);
        $response->assertJSON([
            'error' => 'You must first pay all unpaid statements to close your account'
        ]);
    }

    /**
     * Test closing account removes resources
     * 
     * @group accounts
     */
    public function testClosingAccountRemovesResources()
    {
        Queue::fake();

        $this->createCompanies();

        $statement = $this->createBillableStatement([
            'billing_id'               => $this->billing->id,
            'billing_period_starts_at' => now()->subDays(30)->startOfDay(),
            'billing_period_ends_at'   => now()->endOfDay(),
            'paid_at'                  => now()
        ]);

        $response = $this->json('DELETE', route('delete-account', [
            'confirm_close' => 1
        ]));
        
        $response->assertStatus(200);
        $response->assertJSON([
            'message' => 'Bye'
        ]);
        
        exit('FIX');
    }
}
