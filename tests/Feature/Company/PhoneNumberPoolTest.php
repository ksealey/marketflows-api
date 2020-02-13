<?php

namespace Tests\Feature\Company;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use \App\Models\Account;
use \App\Models\Purchase;
use \App\Models\Company\PhoneNumber;
use \App\Models\Company\PhoneNumberPool;
use \App\Models\Company\AudioClip;
use \App\Models\Company\Campaign;
use \App\Models\PaymentMethod;

class PhoneNumberPoolTest extends TestCase
{
    use \Tests\CreatesUser, RefreshDatabase;

    /**
     * Test listing phone number pools
     *
     * @group feature-phone-number-pools
     */
    public function testList()
    {
        $user = $this->createUser();

        $pool1 = $this->createPhoneNumberPool([
            'company_id'  => $this->company->id,
            'user_id'  => $user->id
        ]);

        $pool2 = $this->createPhoneNumberPool([
            'company_id'  => $this->company->id,
            'user_id'  => $user->id
        ]);

        $response = $this->json('GET', route('list-phone-number-pools', [
            'company' => $this->company->id
        ]), [], $this->authHeaders());
        $response->assertStatus(200);

        $response->assertJson([
            'results'    => [
                [
                    'id'    => $pool1->id,
                    'kind'  => 'PhoneNumberPool'
                ],
                [
                    'id'    => $pool2->id,
                    'kind'  => 'PhoneNumberPool'
                ]
            ],
            'result_count'          => 2,
            'limit'                 => 250,
            'page'                  => 1,
            'total_pages'           => 1,
            'next_page'             => null
        ]);
    }

    /**
     * Test listing phone number pools with a filter
     *
     * @group feature-phone-number-pools
     */
    public function testListWithFilter()
    {
        $user = $this->createUser();

        $pool1 = $this->createPhoneNumberPool([
            'company_id'  => $this->company->id,
            'user_id'  => $user->id
        ]);

        $pool2 = $this->createPhoneNumberPool([
            'company_id'  => $this->company->id,
            'user_id'  => $user->id
        ]);

        $response = $this->json('GET', route('list-phone-number-pools', [
            'company' => $this->company->id
        ]), [
            'search' => $pool2->name
        ], $this->authHeaders());

        $response->assertStatus(200);

        $response->assertJson([
            'results'    => [
                [
                    'id'    => $pool2->id,
                    'kind'  => 'PhoneNumberPool'
                ]
            ],
            'result_count'          => 1,
            'limit'                 => 250,
            'page'                  => 1,
            'total_pages'           => 1,
            'next_page'             => null
        ]);
    }

    /**
     * Test creating an phone number pool for ONLINE/WEBSITE_MANUAL
     * 
     * @group feature-phone-number-pools
     */
    public function testCreateWebsiteManualPool()
    {
        $user   = $this->createUser();

        factory(PaymentMethod::class)->create([
            'account_id' => $this->account->id,
            'user_id' => $this->user->id
        ]);

        $config = $this->createPhoneNumberConfig();

        $pool = factory(PhoneNumberPool::class)->make([
            'category'     => 'ONLINE',
            'sub_category' => 'WEBSITE_MANUAL',
        ]);

        //  Create some numbers to attach to pool
        $number1 = $this->createPhoneNumber([], $config);
        $number2 = $this->createPhoneNumber([], $config);
        $number3 = $this->createPhoneNumber([], $config);
        $number4 = $this->createPhoneNumber([], $config);

        $numberIds = [
            $number1->id,
            $number2->id,
            $number3->id,
            $number4->id
        ];

        $accountBalance = $this->account->balance;

        $response = $this->json('POST', route('create-phone-number-pool', [
            'company' => $this->company->id
        ]), [
            'name'                       => $pool->name,
            'phone_number_config_id'     => $config->id,
            'category'                   => $pool->category,
            'sub_category'               => $pool->sub_category,
            'source'                     => $pool->source,
            'initial_pool_size'          => '5',
            'numbers'                    => json_encode($numberIds),
            'swap_rules'                 => json_encode($pool->swap_rules),
            'referrer_aliases'           => json_encode($pool->referrer_aliases)
        ], $this->authHeaders());

        $response->assertStatus(201);
        $content   = json_decode($response->getContent());
        $newNumber = PhoneNumber::whereNotIn('id', $numberIds)
                                ->where('phone_number_pool_id', $content->id)
                                ->first();              
        $this->assertTrue($newNumber != null);

        $response->assertJSON([
            'company_id'             => $this->company->id,
            'name'                   => $pool->name,
            'kind'                   => 'PhoneNumberPool',
            'phone_number_config_id' => $config->id,
            'category'               => $pool->category,
            'sub_category'           => $pool->sub_category,
            'source'                 => $pool->source,
            'source_param'           => null,
            'swap_rules'             => null,
            'referrer_aliases'       => null,
            'phone_numbers'          => [
                [ 'id' => $number1->id ],
                [ 'id' => $number2->id ],
                [ 'id' => $number3->id ],
                [ 'id' => $number4->id ],
                [ 'id' => $newNumber->id ]
            ]
        ]);

        // Make sure we logged the purchase
        $purchases = Purchase::where('identifier', $newNumber->id)->get();
        $this->assertTrue( count($purchases) == 1 );

        //  Make sure we charged the correct price
        $purchase = $purchases[0];
        $price    = $this->account->price('PhoneNumber.Local');
        $this->assertTrue($purchase->price == $price);

        //  Make sure the account has been deducted from
        $account = Account::find($this->account->id);
        $this->assertTrue($account->balance == $accountBalance - $price);
    }

    /**
     * Test creating an phone number pool for ONLINE/WEBSITE
     * 
     * @group feature-phone-number-pools
     */
    public function testCreateWebsiteSwappingPool()
    {
        $user   = $this->createUser();

        factory(PaymentMethod::class)->create([
            'account_id' => $this->account->id,
            'user_id' => $this->user->id
        ]);

        $config = $this->createPhoneNumberConfig();

        $pool = factory(PhoneNumberPool::class)->make([
            'category'     => 'ONLINE',
            'sub_category' => 'WEBSITE',
        ]);

        //  Create some numbers to attach to pool
        $number1 = $this->createPhoneNumber([], $config);
        $number2 = $this->createPhoneNumber([], $config);
        $number3 = $this->createPhoneNumber([], $config);
        $number4 = $this->createPhoneNumber([], $config);

        $numberIds = [
            $number1->id,
            $number2->id,
            $number3->id,
            $number4->id
        ];

        $accountBalance = $this->account->balance;

        $response = $this->json('POST', route('create-phone-number-pool', [
            'company' => $this->company->id
        ]), [
            'name'                       => $pool->name,
            'phone_number_config_id'     => $config->id,
            'category'                   => $pool->category,
            'sub_category'               => $pool->sub_category,
            'source'                     => $pool->source,
            'source_param'               => $pool->source_param,
            'initial_pool_size'          => '5',
            'numbers'                    => json_encode($numberIds),
            'swap_rules'                 => json_encode($pool->swap_rules),
            'referrer_aliases'           => json_encode($pool->referrer_aliases)
        ], $this->authHeaders());

        $response->assertStatus(201);
        $content   = json_decode($response->getContent());
        $newNumber = PhoneNumber::whereNotIn('id', $numberIds)
                                ->where('phone_number_pool_id', $content->id)
                                ->first();              
        $this->assertTrue($newNumber != null);

        $response->assertJSON([
            'company_id'             => $this->company->id,
            'name'                   => $pool->name,
            'kind'                   => 'PhoneNumberPool',
            'phone_number_config_id' => $config->id,
            'category'               => $pool->category,
            'sub_category'           => $pool->sub_category,
            'source'                 => $pool->source,
            'source_param'           => null,
            'swap_rules'             => $pool->swap_rules,
            'referrer_aliases'       => null,
            'phone_numbers'          => [
                [ 'id' => $number1->id ],
                [ 'id' => $number2->id ],
                [ 'id' => $number3->id ],
                [ 'id' => $number4->id ],
                [ 'id' => $newNumber->id ]
            ]
        ]);

        // Make sure we logged the purchase
        $purchases = Purchase::where('identifier', $newNumber->id)->get();
        $this->assertTrue( count($purchases) == 1 );

        //  Make sure we charged the correct price
        $purchase = $purchases[0];
        $price    = $this->account->price('PhoneNumber.Local');
        $this->assertTrue($purchase->price == $price);

        //  Make sure the account has been deducted from
        $account = Account::find($this->account->id);
        $this->assertTrue($account->balance == $accountBalance - $price);
    }

    /**
     * Test creating an phone number pool for ONLINE/WEBSITE_SESSION
     * 
     * @group feature-phone-number-pools
     */
    public function testCreateWebsiteSessionPool()
    {
        $user   = $this->createUser();

        factory(PaymentMethod::class)->create([
            'account_id' => $this->account->id,
            'user_id' => $this->user->id
        ]);

        $config = $this->createPhoneNumberConfig();

        $pool = factory(PhoneNumberPool::class)->make([
            'category'     => 'ONLINE',
            'sub_category' => 'WEBSITE_SESSION',
        ]);

        //  Create some numbers to attach to pool
        $number1 = $this->createPhoneNumber([], $config);
        $number2 = $this->createPhoneNumber([], $config);
        $number3 = $this->createPhoneNumber([], $config);
        $number4 = $this->createPhoneNumber([], $config);

        $numberIds = [
            $number1->id,
            $number2->id,
            $number3->id,
            $number4->id
        ];

        $accountBalance = $this->account->balance;

        $response = $this->json('POST', route('create-phone-number-pool', [
            'company' => $this->company->id
        ]), [
            'name'                       => $pool->name,
            'phone_number_config_id'     => $config->id,
            'category'                   => $pool->category,
            'sub_category'               => $pool->sub_category,
            'source'                     => $pool->source,
            'source_param'               => $pool->source_param,
            'initial_pool_size'          => '5',
            'numbers'                    => json_encode($numberIds),
            'swap_rules'                 => json_encode($pool->swap_rules),
            'referrer_aliases'           => json_encode($pool->referrer_aliases)
        ], $this->authHeaders());

        $response->assertStatus(201);
        $content   = json_decode($response->getContent());
        $newNumber = PhoneNumber::whereNotIn('id', $numberIds)
                                ->where('phone_number_pool_id', $content->id)
                                ->first();  

        $this->assertTrue($newNumber != null);

        $response->assertJSON([
            'company_id'             => $this->company->id,
            'name'                   => $pool->name,
            'kind'                   => 'PhoneNumberPool',
            'phone_number_config_id' => $config->id,
            'category'               => $pool->category,
            'sub_category'           => $pool->sub_category,
            'source'                 => null,
            'source_param'           => $pool->source_param,
            'swap_rules'             => $pool->swap_rules,
            'referrer_aliases'       => $pool->referrer_aliases,
            'phone_numbers'          => [
                [ 'id' => $number1->id ],
                [ 'id' => $number2->id ],
                [ 'id' => $number3->id ],
                [ 'id' => $number4->id ],
                [ 'id' => $newNumber->id ]
            ]
        ]);

        // Make sure we logged the purchase
        $purchases = Purchase::where('identifier', $newNumber->id)->get();
        $this->assertTrue( count($purchases) == 1 );

        //  Make sure we charged the correct price
        $purchase = $purchases[0];
        $price    = $this->account->price('PhoneNumber.Local');
        $this->assertTrue($purchase->price == $price);

        //  Make sure the account has been deducted from
        $account = Account::find($this->account->id);
        $this->assertTrue($account->balance == $accountBalance - $price);
    }

    /**
     * Test reading an phone number pool
     * 
     * @group feature-phone-number-pools
     */
    public function testRead()
    {
        $user = $this->createUser();

        $pool = $this->createPhoneNumberPool([
            'company_id'  => $this->company->id,
            'user_id'  =>  $user->id
        ]);

        $number = $this->createPhoneNumber([
            'phone_number_pool_id' => $pool->id
        ]);

        $response = $this->json('GET', route('read-phone-number-pool', [
            'company'         => $this->company->id,
            'phoneNumberPool' => $pool->id,
        ]), [], $this->authHeaders());

        $response->assertStatus(200);
        $response->assertJSON([
            'id'                        => $pool->id,
            'category'                  => $pool->category,
            'sub_category'              => $pool->sub_category,
            'phone_number_config_id'    => $pool->phone_number_config_id,
            'kind'                      => 'PhoneNumberPool',
            'phone_numbers'             => [
                [
                    'id'   => $number->id,
                    'kind' => 'PhoneNumber'
                ]
            ],
        ]);
    }

    /**
     * Test updating an phone number pool
     * 
     * @group feature-phone-number-pools
     */
    public function testUpdate()
    {
        $user = $this->createUser();

        $pool = $this->createPhoneNumberPool([
            'company_id' => $this->company->id,
            'user_id' => $user->id
        ]);

        $updatedPool = factory(PhoneNumberPool::class)->make([
            'category'     => 'ONLINE',
            'sub_category' => 'WEBSITE_MANUAL'
        ]);

        $config = $this->createPhoneNumberConfig();

        $response = $this->json('PUT', route('update-phone-number-pool', [
            'company'         => $this->company->id,
            'phoneNumberPool' => $pool->id,
        ]), [
            'name'                   => $updatedPool->name,
            'category'               => $updatedPool->category,
            'sub_category'           => $updatedPool->sub_category,
            'source'                 => $updatedPool->source,
            'swap_rules'             => json_encode($updatedPool->swap_rules),
            'referrer_aliases'       => json_encode($updatedPool->referrer_aliases),
            'phone_number_config_id' => $config->id
        ], $this->authHeaders());

        $response->assertStatus(200);

        $response->assertJson([
            'id'                     => $pool->id,
            'name'                   => $updatedPool->name,
            'category'               => $updatedPool->category,
            'sub_category'           => $updatedPool->sub_category,
            'source'                 => $updatedPool->source,
            'phone_number_config_id' => $config->id,
            'swap_rules'             => null,
            'referrer_aliases'       => null,
            'phone_numbers'          => [],
            'kind'                   => 'PhoneNumberPool'
        ]);

        $this->assertTrue(PhoneNumberPool::find($pool->id)->name == $updatedPool->name);
    }

    /**
     * Test deleting a phone number pool
     * 
     * @group feature-phone-number-pools
     */
    public function testDelete()
    {
        $user = $this->createUser();

        $pool = $this->createPhoneNumberPool();

        $response = $this->json('DELETE',  route('delete-phone-number-pool', [
            'company'         => $this->company->id,
            'phoneNumberPool' => $pool->id,
        ]), [], $this->authHeaders());
        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'deleted',
        ]);

        $this->assertTrue(PhoneNumberPool::find($pool->id) == null);
    }

    /**
     * Test deleting a phone number pool with attached phone numbers does not delete pool
     * 
     * @group feature-phone-number-pools-
     */
    public function testDeleteWithAttachedNumber()
    {
        $user = $this->createUser();

        $pool = $this->createPhoneNumberPool();

        $number = $this->createPhoneNumber([
            'phone_number_pool_id' => $pool->id
        ]);

        $response = $this->json('DELETE',  route('delete-phone-number-pool', [
            'company'         => $this->company->id,
            'phoneNumberPool' => $pool->id,
        ]), [], $this->authHeaders());
        $response->assertStatus(400);
        $response->assertJson([
            'error' => 'This phone number pool is in use - release or re-assign all attached phone numbers and try again.',
        ]);

        $this->assertTrue(PhoneNumberPool::find($pool->id) != null);
    }
}
