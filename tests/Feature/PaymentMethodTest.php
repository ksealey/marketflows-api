<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;
use \Stripe\Stripe;
use \Stripe\Customer;
use App\Jobs\PayUnpaidStatementsJob;

class PaymentMethodTest extends TestCase
{
    use \Tests\CreatesAccount;

    /**
     * Test creating a payment method successfully
     * 
     * @group payment-methods
     */
    public function testCreatePaymentMethod()
    {
        Queue::fake();

        Stripe::setApiKey(config('services.stripe.secret'));
        $customer = Customer::create([
            'description' => str_random(40),
            'source'      => 'tok_bypassPending'
        ]);

        $this->billing->external_id = $customer->id;
        $this->billing->save();

        $response = $this->json('POST', route('create-payment-method'), [
            'token' => 'tok_bypassPendingInternational',
            'primary_method' => false
        ]);

        $response->assertStatus(201);
        $response->assertJSON([
            'type'           => 'credit',
            'brand'          => 'Visa',
            'primary_method' => false,
            'kind'           => 'PaymentMethod',
            'created_by'     => $this->user->id
        ]);

        $methodId = null;

        Queue::assertPushed(PayUnpaidStatementsJob::class, function($job) use(&$methodId){
            $methodId = $job->paymentMethod->id;

            return $job->paymentMethod->account_id == $this->account->id;
        });

        $response = $this->json('PUT', route('make-default-payment-method', [
            'paymentMethod' => $methodId
        ]));

        $response->assertStatus(200);
        $response->assertJSON([
            'id'             => $methodId,
            'type'           => 'credit',
            'brand'          => 'Visa',
            'primary_method' => true,
            'kind'           => 'PaymentMethod',
            'created_by'     => $this->user->id
        ]);
    }

    /**
     * Test reading payment method
     * 
     * @group payment-methods
     */
    public function testRead()
    {
        $pm = $this->createPaymentMethod();

        $response = $this->json('GET', route('read-payment-method', [
            'paymentMethod' => $pm->id
        ]));

        $response->assertStatus(200);
        $response->assertJSON([
            'id'             => $pm->id,
            'type'           => 'credit',
            'brand'          => 'Visa',
            'primary_method' => true,
            'kind'           => 'PaymentMethod',
            'created_by'     => $this->user->id
        ]);
    }

    /**
     * Test delete fails when primary
     * 
     * @group payment-methods
     */
    public function testDeleteFailsWhenPrimary()
    {
        $pm = $this->createPaymentMethod();

        $response = $this->json('DELETE', route('read-payment-method', [
            'paymentMethod' => $pm->id
        ]));

        $response->assertStatus(400);
        $response->assertJSON([
            "error" => "You cannot delete your primary payment method"
        ]);
    }

    /**
     * Test delete
     * 
     * @group payment-methods
     */
    public function testDelete()
    {
        $pm = $this->createPaymentMethod();
        $pm->primary_method = 0;
        $pm->save();

        $response = $this->json('DELETE', route('read-payment-method', [
            'paymentMethod' => $pm->id
        ]));

        $response->assertStatus(200);
        $response->assertJSON([
            "message" => "Deleted"
        ]);
    }
}
