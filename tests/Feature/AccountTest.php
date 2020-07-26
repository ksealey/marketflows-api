<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\Account;
use App\Models\Company;
use App\Models\Company\PhoneNumber;
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
        $newName = 'UPDATED - Account';
        $response = $this->json('PUT', route('update-account'), [
            'name' => $newName
        ]);
        $response->assertStatus(200);
        $response->assertJSON([
            'id' => $this->account->id,
            'name' => $newName,
        ]);

        $account = Account::find($this->account->id);

        $this->assertEquals($account->name, $newName);
    }
}
