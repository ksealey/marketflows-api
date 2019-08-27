<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use \App\Models\PaymentMethod;

class PaymentMethodTest extends TestCase
{
    use \Tests\CreatesUser;

    /**
     * Test creating a payment method
     * 
     * @group integrate-payment-methods
     */
    public function testCreate()
    {
        $user         = $this->createUser();
        $stripeToken  = 'tok_visa';
        $declineToken = 'tok_chargeCustomerFail';

        $response = $this->json('POST', 'http://localhost/v1/payment-methods', [
            'token' => $stripeToken
        ], $this->authHeaders());

        $response->assertJson([
            'message' => 'created'
        ]);

        $response->assertJsonStructure([
            'payment_method' => [
                'id'
            ]
        ]);

        $response->assertStatus(201);
    }

    /**
     * Test showing a payment method
     * 
     * @group integrate-payment-methods
     */
    public function testRead()
    {
        $user         = $this->createUser();
        
        $paymentMethod = factory(PaymentMethod::class)->create([
            'account_id' => $user->account_id,
            'created_by' => $user->id
        ]);

        $response = $this->json('GET', 'http://localhost/v1/payment-methods/' . $paymentMethod->id, [], $this->authHeaders());

        $response->assertJson([
            'message' => 'success'
        ]);

        $response->assertJsonStructure([
            'payment_method' => [
                'id'
            ]
        ]);

        $response->assertStatus(200);
    }

    /**
     * Test setting payment method as default
     * 
     * @group integrate-payment-methods
     */
    public function testMakeDefault()
    {
        $user = $this->createUser();
        
        $paymentMethod = factory(PaymentMethod::class)->create([
            'account_id' => $user->account_id,
            'created_by'     => $user->id,
            'primary_method' => true
        ]);

        $paymentMethod2 = factory(PaymentMethod::class)->create([
            'account_id' => $user->account_id,
            'created_by'     => $user->id,
            'primary_method' => false
        ]);

        $this->assertTrue($paymentMethod->primary_method == true);
        $this->assertTrue($paymentMethod2->primary_method == false);

        $response = $this->json('PUT', 'http://localhost/v1/payment-methods/' . $paymentMethod2->id . '/make-default', [], $this->authHeaders());
        $response->assertStatus(200);

        $response->assertJson([
            'message' => 'updated',
        ]);

        $this->assertTrue(PaymentMethod::find($paymentMethod->id)->primary_method == false);
        $this->assertTrue(PaymentMethod::find($paymentMethod2->id)->primary_method == true);
    }

    /**
     * Test deleting a payment method
     * 
     * @group integrate-payment-methods
     */
    public function testDelete()
    {
        $user = $this->createUser();
        
        $paymentMethod = factory(PaymentMethod::class)->create([
            'account_id'     => $user->account_id,
            'created_by'     => $user->id,
            'primary_method' => false
        ]);

        $this->assertTrue(PaymentMethod::find($paymentMethod->id) != null);

        $response = $this->json('DELETE', 'http://localhost/v1/payment-methods/' . $paymentMethod->id, [], $this->authHeaders());

        $response->assertStatus(200);

        $response->assertJson([
            'message' => 'deleted'
        ]);

        $this->assertTrue(PaymentMethod::find($paymentMethod->id) == null);
    }

    /**
     * Test deleting a primary payment method
     * 
     * @group integrate-payment-methods
     */
    public function testDeletePrimaryMethod()
    {
        $user = $this->createUser();
        
        $paymentMethod = factory(PaymentMethod::class)->create([
            'account_id' => $user->account_id,
            'created_by'     => $user->id,
            'primary_method' => true
        ]);

        $this->assertTrue(PaymentMethod::find($paymentMethod->id) != null);

        $response = $this->json('DELETE', 'http://localhost/v1/payment-methods/' . $paymentMethod->id, [], $this->authHeaders());

        $response->assertStatus(400);

        $response->assertJsonStructure([
            'error'
        ]);

        $this->assertTrue(PaymentMethod::find($paymentMethod->id) != null);
    }

    /**
     * Test listing payment methods
     * 
     * @group integrate-payment-methods
     */
    public function testList()
    {
        $user = $this->createUser();
        
        $paymentMethod = factory(PaymentMethod::class)->create([
            'account_id'     => $user->account_id,
            'created_by'     => $user->id,
            'primary_method' => true
        ]);

        $paymentMethod2 = factory(PaymentMethod::class)->create([
            'account_id'     => $user->account_id,
            'created_by'     => $user->id,
            'primary_method' => true
        ]);

        $paymentMethod3 = factory(PaymentMethod::class)->create([
            'account_id'     => $user->account_id,
            'created_by'     => $user->id,
            'primary_method' => true
        ]);

        $response = $this->json('GET', 'http://localhost/v1/payment-methods', [], $this->authHeaders());

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'payment_methods' => [
                [
                    'id'
                ],
                [
                    'id'
                ]
            ]
        ]);
    }
   
}
