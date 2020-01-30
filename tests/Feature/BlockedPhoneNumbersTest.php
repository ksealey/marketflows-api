<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\BlockedPhoneNumber;

class BlockedPhoneNumbersTest extends TestCase
{
    use \Tests\CreatesUser, RefreshDatabase;

    /**
     * Test Creating a blocked phone number for an account
     *
     * @group feature-blocked-phone-numbers
     */
    public function testCreateForAccount()
    {
        $user          = $this->createUser();

        $blockedNumber = factory(BlockedPhoneNumber::class)->make();

        $response = $this->json('POST', route('create-blocked-phone-number'), [
            'name'   => $blockedNumber->name,
            'number' => $blockedNumber->number
        ], $this->authHeaders());

        $response->assertStatus(201);

        $response->assertJSON([
            'number'        => $blockedNumber->number,
            'name'          => $blockedNumber->name,
            'created_by'    => $user->id,
            'company_id'    => null,
            'kind'          => 'BlockedPhoneNumber'
        ]);
    }

    /**
     * Test Reading a blocked phone number for an account
     *
     * @group feature-blocked-phone-numbers
     */
    public function testReadForAccount()
    {
        $user          = $this->createUser();

        $blockedNumber = factory(BlockedPhoneNumber::class)->create([
            'account_id' => $user->account_id,
            'created_by' => $user->id
        ]);

        $response = $this->json('GET', route('read-blocked-phone-number', [
            'blockedPhoneNumber' =>  $blockedNumber->id
        ]), [], $this->authHeaders());

        $response->assertStatus(200);

        $response->assertJSON([
            'number'        => $blockedNumber->number,
            'name'          => $blockedNumber->name,
            'created_by'    => $user->id,
            'company_id'    => null,
            'kind'          => 'BlockedPhoneNumber'
        ]);
    }

    /**
     * Test Deleting a blocked phone number for an account
     *
     * @group feature-blocked-phone-numbers
     */
    public function testDeleteForAccount()
    {
        $user          = $this->createUser();

        $blockedNumber = factory(BlockedPhoneNumber::class)->create([
            'account_id' => $user->account_id,
            'created_by' => $user->id
        ]);

        $response = $this->json('DELETE', route('delete-blocked-phone-number', [
            'blockedPhoneNumber' =>  $blockedNumber->id
        ]), [],  $this->authHeaders());

        $response->assertStatus(200);

        $response->assertJSON([
            'message' => 'Deleted.'
        ]);

        $blockedNumber = BlockedPhoneNumber::find($blockedNumber->id);

        $this->assertNull($blockedNumber);
    }

    /**
     * Test Creating a blocked phone number for a company
     *
     * @group feature-blocked-phone-numbers
     */
    public function testCreateForCompany()
    {
        $user          = $this->createUser();

        $blockedNumber = factory(BlockedPhoneNumber::class)->make();

        $response = $this->json('POST', route('create-company-blocked-phone-number', [
            'companyId' => $this->company->id
        ]), [
            'name'   => $blockedNumber->name,
            'number' => $blockedNumber->number,
            'company_id' => $this->company->id
        ], $this->authHeaders());

        $response->assertStatus(201);

        $response->assertJSON([
            'number'        => $blockedNumber->number,
            'name'          => $blockedNumber->name,
            'created_by'    => $user->id,
            'company_id'    => $this->company->id,
            'kind'          => 'BlockedPhoneNumber'
        ]);
    }

    /**
     * Test Reading a blocked phone number for a company
     *
     * @group feature-blocked-phone-numbers
     */
    public function testReadForCompany()
    {
        $user = $this->createUser();

        $blockedNumber = factory(BlockedPhoneNumber::class)->create([
            'account_id' => $user->account_id,
            'company_id' => $this->company->id,
            'created_by' => $user->id
        ]);

        $response = $this->json('GET', route('read-blocked-phone-number', [
            'companyId'          => $this->company->id,
            'blockedPhoneNumber' =>  $blockedNumber->id
        ]), [], $this->authHeaders());

        $response->assertStatus(200);

        $response->assertJSON([
            'number'        => $blockedNumber->number,
            'name'          => $blockedNumber->name,
            'created_by'    => $user->id,
            'company_id'    => $this->company->id,
            'kind'          => 'BlockedPhoneNumber'
        ]);
    }

    /**
     * Test Listing  blocked phone numbers for an company
     *
     * @group feature-blocked-phone-numbers
     */
    public function testDeleteForCompany()
    {
        $user          = $this->createUser();

        $blockedNumber = factory(BlockedPhoneNumber::class)->create([
            'account_id' => $user->account_id,
            'created_by' => $user->id,
            'company_id' => $this->company->id,
        ]);

        $response = $this->json('DELETE', route('delete-blocked-phone-number', [
            'companyId'          => $this->company->id,
            'blockedPhoneNumber' =>  $blockedNumber->id
        ]), [],  $this->authHeaders());

        $response->assertStatus(200);

        $response->assertJSON([
            'message' => 'Deleted.'
        ]);

        $blockedNumber = BlockedPhoneNumber::find($blockedNumber->id);
        
        $this->assertNull($blockedNumber);
    }
}
