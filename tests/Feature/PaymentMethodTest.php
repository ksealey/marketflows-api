<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Queue;
use App\Helpers\PaymentManager;
use App\Models\PaymentMethod;
use Tests\TestCase;
use \Stripe\Stripe;
use \Stripe\Customer;
use App;

class PaymentMethodTest extends TestCase
{
    use \Tests\CreatesAccount;

    /**
     * Test creating a payment intent
     * 
     * @group payment-methods
     */
    public function testCreateSetupIntent()
    {
        Stripe::setApiKey(config('services.stripe.secret'));
        $customer = Customer::create([
            'description' => str_random(40)
        ]);

        $this->billing->external_id = $customer->id;
        $this->billing->save();

        $response = $this->json('POST', route('create-payment-intent'));
        $response->assertJSONStructure([
            "client_secret",
            "customer"
        ]);
    }

    /**
     * Test creating a payment method successfully
     * 
     * @group payment-methods
     */
    public function testCreatePaymentMethod()
    {
        $paymentMethodId = str_random(20);
        $paymentMethods  = [
            (object)[
                'id' => str_random(20),
                'card' => (object)[
                    'exp_year' => now()->addYears(1)->format('Y'),
                    'exp_month'=> now()->format('m'),
                    'last4'   => mt_rand(1111, 9999),
                    'funding'  => 'debit',
                    'brand'    => 'visa'
                ]
            ],
            (object)[
                'id' => $paymentMethodId,
                'card' => (object)[
                    'exp_year' => now()->addYears(1)->format('Y'),
                    'exp_month'=> now()->format('m'),
                    'last4'   => mt_rand(1111, 9999),
                    'funding'  => 'credit',
                    'brand'    => 'visa'
                ]
            ],
        ];

        $this->mock(PaymentManager::class, function($mock) use($paymentMethods){
            $mock->shouldReceive('getPaymentMethods')
                 ->andReturn($paymentMethods)
                 ->once();
        });

        $response = $this->json('POST', route('create-payment-method'), [
            'payment_method_id' => $paymentMethodId,
            'primary_method'    => 1
        ]);

        $response->assertStatus(201);
        $response->assertJSON([
            'type'           => 'credit',
            'brand'          => 'Visa',
            'primary_method' => true,
            'kind'           => 'PaymentMethod',
            'created_by'     => $this->user->id
        ]);

        $this->assertDatabaseHas('payment_methods', [
            'account_id' => $this->account->id,
            'id' => $response['id']
        ]);
    }

    /**
     * Test making a payment method default
     * 
     * @group payment-methods
     */
    public function testMakeDefault()
    {   
        $paymentMethod = factory(PaymentMethod::class)->create([
            'account_id' => $this->account->id,
            'created_by' => $this->user->id,
            'primary_method' => false
        ]);

        $response = $this->json('PUT', route('make-default-payment-method', [
            'paymentMethod' => $paymentMethod->id
        ]));

        $response->assertStatus(200);
        $response->assertJSON([
            'id'             => $paymentMethod->id,
            'type'           => 'credit',
            'brand'          => 'Visa',
            'primary_method' => true,
            'kind'           => 'PaymentMethod',
            'created_by'     => $this->user->id
        ]);

        $this->assertDatabaseHas('payment_methods', [
            'id' => $paymentMethod->id,
            'primary_method' => true
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

    /**
     * Test payment methods requiring authorization
     * 
     * @group payment-methods
     */
    public function testPaymentFailedAuthentication()
    {
        Stripe::setApiKey(config('services.stripe.secret'));
        $customer = Customer::create([
            'description' => str_random(40)
        ]);
        $this->billing->external_id = $customer->id;
        $this->billing->save();

        $paymentMethod = factory(PaymentMethod::class)->create([
            'account_id'        => $this->account->id,
            'created_by'        => $this->user->id,
            'external_id'       => 'pm_card_authenticationRequired',
            'primary_method'    => true
        ]);

        $statement      = $this->createBillableStatement();
        $paymentManager = App::make(PaymentManager::class);
        $result         = $paymentManager->charge($paymentMethod, $statement);
        
        $this->assertDatabaseHas('payment_methods', [
            'id'                    => $paymentMethod->id,
            'last_error_intent_id'  => $result->intent->id,
            'last_error_code'       => 'authentication_required'
        ]);

        $this->assertDatabaseHas('billing_statements', [
            'id'         => $statement->id,
            'intent_id'  => $result->intent->id,
            'payment_attempts' => 1
        ]);

        $this->partialMock(PaymentManager::class, function($mock) use($result){
            $mock->shouldReceive('getIntent')
                 ->with($result->intent->id)
                 ->andReturn((object)[
                     'id'     => $result->intent->id,
                     'status' => 'succeeded'
                 ]);
        });

        //  Now authenticate
        $response = $this->json('PUT', route('authenticate-payment-method', [
            'paymentMethod' => $paymentMethod->id
        ]));
        $response->assertStatus(200);
        $response->assertJSON([
            'id'   => $paymentMethod->id,
            'kind' => 'PaymentMethod',
            'last_error_code' => null,
            'last_error_intent_id' => null
        ]);

        // Make sure the statement was paid
        $this->assertDatabaseHas('payment_methods', [
            'id'                    => $paymentMethod->id,
            'last_error'            => null,
            'last_error_code'       => null,
            'last_error_intent_id'  => null,
            'last_error_intent_secret' => null
        ]);

        $this->assertDatabaseMissing('billing_statements', [
            'id'            => $statement->id,
            'paid_at'       => null,
        ]);

        $this->assertDatabaseHas('payments', [
            'billing_statement_id' => $statement->id,
            'payment_method_id'    => $paymentMethod->id,
            'total'                => $statement->total
        ]);
    }
}
