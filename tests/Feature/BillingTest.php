<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Queue;
use App\Models\Alert;
use App\Models\Account;
use App\Models\Billing;
use App\Jobs\BillAccountJob;
use App\Models\BillingStatement;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Company\PhoneNumber;
use App\Mail\BillingReceipt;
use App\Mail\PaymentMethodFailed;
use App\Mail\AccountUnsuspended;
use App\Helpers\PaymentManager;
use Artisan;
use Mail;

class BillingTest extends TestCase
{
    use \Tests\CreatesAccount;

    /**
     * Test listing statements
     * 
     * @group billing
     */
    public function testListingStatements()
    {
        $statements = [];
        $count      = mt_rand(1, 3);
        for( $i = 0; $i < $count; $i++){
            $statements[] = $this->createBillableStatement([
                'billing_id'               => $this->billing->id,
                'billing_period_starts_at' => now()->subDays(30)->startOfDay(),
                'billing_period_ends_at'   => now()->endOfDay()
            ])->toArray();
        }

        $response = $this->json('GET', route('list-statements'));
        
        $response->assertStatus(200);
        $response->assertJSON([
            "result_count" => $count,
            "limit"        => 250,
            "page"         => 1,
            "total_pages"  => 1,
            "next_page"    => null,
            "results"      => $statements
        ]);
    }

    /**
     * Test reading statements
     * 
     * @group billing
     */
    public function testReadStatement()
    {
        $statements = [];
        $statement  = $this->createBillableStatement([
            'billing_id'               => $this->billing->id,
            'billing_period_starts_at' => now()->subDays(30)->startOfDay(),
            'billing_period_ends_at'   => now()->endOfDay()
        ]);

        $response = $this->json('GET', route('read-statement', [
            'billingStatement' => $statement->id
        ]));
        $response->assertStatus(200);
        $response->assertJSON($statement->toArray());
    }

    /**
     * Test that billing jobs are dispatched
     * 
     * @group billing
     */
    public function testBillingJobsDispatched()
    {
        $this->billing->billing_period_starts_at = now()->subDays(7);
        $this->billing->billing_period_ends_at   = now()->subMinutes(2);
        $this->billing->save();

        Queue::fake();

        Artisan::call('bill-accounts');

        Queue::assertPushed(BillAccountJob::class, function($job){
            return $job->billing->id == $this->billing->id;
        });
    }

    /**
     * Test that billing jobs are not dispatched when billing is locked
     * 
     * @group billing
     */
    public function testBillingJobsNotDispatchedWhenLocked()
    {
        $this->billing->billing_period_starts_at = now()->subDays(7);
        $this->billing->billing_period_ends_at   = now()->subMinutes(2);
        $this->billing->locked_at                = now();
        $this->billing->save();

        Queue::fake();

        Artisan::call('bill-accounts');

        Queue::assertNotPushed(BillAccountJob::class);
    }

    /**
     * Test billing job
     * 
     * @group billing
     */
    public function testBillingJobWorks()
    {
        Mail::fake();

        $companyCount = 5;
        for( $i = 0; $i < $companyCount; $i++ ){
            $company = $this->createCompany();
        }

        //  Set billing period
        $this->billing->billing_period_starts_at = now()->subDays(7)->startOfDay();
        $this->billing->billing_period_ends_at   = now()->endOfDay();
        $this->billing->save();

        //  Get payment method to use
        $paymentMethod = $this->account->primary_payment_method;
        $totalOwed     = $this->billing->currentTotal();

        $this->partialMock('App\Helpers\PaymentManager', function($mock) use($paymentMethod, $totalOwed){
            $charge = (object)[
                'id'            => str_random(10),
                'customer'      => $this->billing->external_id,
                'source'        => $paymentMethod->external_id,
                'amount'        => $totalOwed,
                'currency'      => 'usd',
                'description'   => 'MarketFlows, LLC'
            ];

            $mock->shouldReceive('createCharge')
                 ->once()
                 ->with(PaymentMethod::class, $totalOwed, 'MarketFlows, LLC')
                 ->andReturn($charge);

            $mock->shouldReceive('createPayment')
                 ->once()
                 ->with($charge, PaymentMethod::class, $totalOwed)
                 ->andReturn(factory(Payment::class)->create([
                    'payment_method_id' => $paymentMethod->id,
                    'external_id'       => $charge->id,
                    'total'             => $totalOwed
                 ]));
        });
        
        BillAccountJob::dispatch($this->billing);

        //  
        //  Make sure a statement was created and paid
        //
        $statement = BillingStatement::where('billing_id', $this->billing->id)->first();

        $this->assertNotNull($statement);
        $this->assertEquals($statement->total(), $this->billing->currentTotal());
        $this->assertEquals($statement->total(), $statement->payment->total);
        $this->assertNotNull($statement->paid_at);

        //
        //  Make sure the mail was sent
        //  
        Mail::assertQueued(BillingReceipt::class, function($mail){
            return $mail->hasTo($this->user->email);
        });
    }

    /**
     * Test billing job fails gracefully
     * 
     * @group billing
     */
    public function testBillingJobFailsGracefully()
    {
        Mail::fake();
        $companyCount = 5;
        for( $i = 0; $i < $companyCount; $i++ ){
            $company = $this->createCompany();
        }

        $this->paymentMethod->external_id = 'tok_chargeDeclined';
        $this->paymentMethod->save();

        $this->billing->billing_period_starts_at = now()->subDays(7);
        $this->billing->billing_period_ends_at   = now()->subMinutes(2);
        $this->billing->save();

        $this->mock('App\Helpers\PaymentManager', function($mock){
            $mock->shouldReceive('charge')
                 ->once()
                 ->andReturn(false);
        });
        
        BillAccountJob::dispatch($this->billing);

        //  
        //  Make sure a statement was created and not paid
        //
        $statement = BillingStatement::where('billing_id', $this->billing->id)->first();
        $this->assertNotNull($statement);
        $this->assertNull($statement->payment_id);
        $this->assertNull($statement->paid_at);

        //
        //  Make sure the payment failure mail was sent
        //  
        Mail::assertQueued(PaymentMethodFailed::class, function($mail){
            return $mail->hasTo($this->user->email);
        });
    }

    /**
     * Test that the billing calculates properly
     * 
     * @group billing
     */
    public function testBillingCalculatesProperly()
    {
        $billing = $this->billing;

        $billing->billing_period_starts_at = now()->subDays(7)->startOfDay();
        $billing->billing_period_ends_at   = now()->endOfDay();
        $billing->save();

        $billingPeriodStart = $billing->billing_period_starts_at;
        $billingPeriodEnd   = $billing->billing_period_ends_at;

        // populateUsage($companyCount, $localNumberCount, $tollFreeNumberCount, $localCallsPerNumber, $tollFreeCallsPerNumber)
        $companyCount           = 2;
        $localNumberCount       = 11;
        $tollFreeNumberCount    = 1;
        $localCallsPerNumber    = 10;
        $tollFreeCallsPerNumber = 10;

        $this->populateUsage($companyCount, $localNumberCount, $tollFreeNumberCount, $localCallsPerNumber, $tollFreeCallsPerNumber);
        
        $serviceQuantity  = $billing->quantity(Billing::ITEM_SERVICE, $billingPeriodStart, $billingPeriodEnd);
        $serviceTotal     = $billing->total(Billing::ITEM_SERVICE, $serviceQuantity);
        $this->assertEquals($serviceQuantity, 1);
        $this->assertEquals($serviceTotal, Billing::COST_SERVICE);

        $localNumberQuantity    = $billing->quantity(Billing::ITEM_NUMBERS_LOCAL, $billingPeriodStart, $billingPeriodEnd);
        $localNumberTotal       = $billing->total(Billing::ITEM_NUMBERS_LOCAL, $localNumberQuantity);
        $this->assertEquals($localNumberQuantity, $companyCount * $localNumberCount);
        $this->assertEquals($localNumberTotal, 12 * Billing::COST_NUMBERS_LOCAL); // tier of 10

        $tollFreeNumberQuantity = $billing->quantity(Billing::ITEM_NUMBERS_TOLL_FREE, $billingPeriodStart, $billingPeriodEnd);
        $tollFreeNumberTotal    = $billing->total(Billing::ITEM_NUMBERS_TOLL_FREE, $tollFreeNumberQuantity);
        $this->assertEquals($tollFreeNumberQuantity, 2);
        $this->assertEquals($tollFreeNumberTotal, 2 * Billing::COST_NUMBERS_TOLL_FREE);

        $localMinutesQuantity   = $billing->quantity(Billing::ITEM_MINUTES_LOCAL, $billingPeriodStart, $billingPeriodEnd);
        $localMinutesTotal      = $billing->total(Billing::ITEM_MINUTES_LOCAL, $localMinutesQuantity);
        $this->assertEquals($localMinutesQuantity, $companyCount * $localNumberCount * $localCallsPerNumber);
        $this->assertEquals($localMinutesTotal, 0);

        $tollFreeMinutesQuantity= $billing->quantity(Billing::ITEM_MINUTES_TOLL_FREE, $billingPeriodStart, $billingPeriodEnd);
        $tollFreeMinutesTotal   = $billing->total(Billing::ITEM_MINUTES_TOLL_FREE, $tollFreeMinutesQuantity);
        $this->assertEquals($tollFreeMinutesQuantity, $companyCount * $tollFreeNumberCount * $tollFreeCallsPerNumber);
        $this->assertEquals($tollFreeMinutesTotal, ($companyCount * $tollFreeNumberCount * $tollFreeCallsPerNumber) * Billing::COST_MINUTES_TOLL_FREE);

        $transMinutesQuantity   = $billing->quantity(Billing::ITEM_MINUTES_TRANSCRIPTION, $billingPeriodStart, $billingPeriodEnd);
        $transMinutesTotal      = $billing->total(Billing::ITEM_MINUTES_TRANSCRIPTION, $transMinutesQuantity);
        $this->assertEquals($transMinutesQuantity, $localMinutesQuantity + $tollFreeMinutesQuantity);
        $this->assertEquals($transMinutesTotal, ($localMinutesQuantity + $tollFreeMinutesQuantity) * Billing::COST_MINUTES_TRANSCRIPTION);

        $storageQuantity        = $billing->quantity(Billing::ITEM_STORAGE_GB, $billingPeriodStart, $billingPeriodEnd);
        $storageTotal           = $billing->total(Billing::ITEM_STORAGE_GB, $storageQuantity);
        $this->assertEquals($storageQuantity, 240);
        $this->assertEquals($storageTotal, (240 - Billing::TIER_STORAGE_GB) * Billing::COST_STORAGE_GB);

        $intialTotal = $serviceTotal + $localNumberTotal + $tollFreeNumberTotal + $localMinutesTotal + $tollFreeMinutesTotal + $transMinutesTotal + $storageTotal;
        $this->assertEquals($billing->currentTotal(), $intialTotal);
        
        $number = PhoneNumber::where('account_id', $this->account->id)
                            ->where('type', 'Local')
                            ->orderBy('created_at', 'DESC')
                            ->first();

        //  Delete a number out of the range and make sure it's not counted in total
        $number->deleted_at = now()->subDays(8);
        $number->save();

        $newLocalNumberQuantity = $billing->quantity(Billing::ITEM_NUMBERS_LOCAL, $billingPeriodStart, $billingPeriodEnd);
        $this->assertEquals($localNumberQuantity - 1, $newLocalNumberQuantity);
        $this->assertEquals($billing->currentTotal(), $intialTotal - $billing->price(Billing::ITEM_NUMBERS_LOCAL));

        //  Bring deleted number into range and make sure it's counted
        $number->deleted_at = now()->subDays(1);
        $number->save();

        $newLocalNumberQuantity = $billing->quantity(Billing::ITEM_NUMBERS_LOCAL, $billingPeriodStart, $billingPeriodEnd);
        $this->assertEquals($localNumberQuantity, $newLocalNumberQuantity);
        $this->assertEquals($billing->currentTotal(), $intialTotal);

        //  Undelete number and bring it past upper range
        $number->deleted_at = null;
        $number->created_at = now()->addDays(10);
        $number->save();

        $newLocalNumberQuantity = $billing->quantity(Billing::ITEM_NUMBERS_LOCAL, $billingPeriodStart, $billingPeriodEnd);
        $this->assertEquals($localNumberQuantity - 1, $newLocalNumberQuantity);
        $this->assertEquals($billing->currentTotal(), $intialTotal - $billing->price(Billing::ITEM_NUMBERS_LOCAL));
    }

    /**
     * Test fetching current total
     * 
     * @group billing
     */
    public function testFetchingCurrentTotal()
    {
        $companyCount           = 2;
        $localNumberCount       = 11;
        $tollFreeNumberCount    = 1;
        $localCallsPerNumber    = 10;
        $tollFreeCallsPerNumber = 10;

        $this->populateUsage($companyCount, $localNumberCount, $tollFreeNumberCount, $localCallsPerNumber, $tollFreeCallsPerNumber);
        
        $response = $this->json('GET', route('current-statement'));
        $response->assertStatus(200);
        $response->assertJSON([
            'total' => $this->billing->currentTotal(),
            'items' => []
        ]);
    }

    /**
     * Test paying a statement
     * 
     * @group billing
     */
    public function testPayStatement()
    {
        Mail::fake();

        //  Suspend
        $this->account->suspended_at        = now();
        $this->account->suspension_code     = Account::SUSPENSION_CODE_OUSTANDING_BALANCE;
        $this->account->suspension_message  = Account::SUSPENSION_CODE_OUSTANDING_BALANCE;
        $this->account->save();

        $this->billing->suspension_warnings = 3;
        $this->billing->next_suspension_warning_at = now();
        $this->billing->save();

        //  Add alert
        $alert = factory(Alert::class)->create([
            'user_id'  => $this->user->id,
            'category' => Alert::CATEGORY_PAYMENT
        ]);

        $paymentMethod = $this->createPaymentMethod();

        $payment = factory(Payment::class)->create([
            'payment_method_id' => $paymentMethod->id,
            'external_id'       => str_random(16),
            'total'             => 100.50
        ]);

        $this->mock(PaymentManager::class, function($mock) use($payment){
            $mock->shouldReceive('charge')
                 ->once()
                 ->andReturn($payment);
        });

        $statement = $this->createBillableStatement([
            'billing_id'               => $this->billing->id,
            'billing_period_starts_at' => now()->subDays(30)->startOfDay(),
            'billing_period_ends_at'   => now()->endOfDay()
        ]);

        $response = $this->json('POST', route('pay-statement', [
            'billingStatement' => $statement->id,
            'payment_method_id'=> $paymentMethod->id
        ]));

        $response->assertStatus(201);
        $response->assertJSON($payment->toArray());

        //  Make sure statement is paid
        $statement = BillingStatement::find($statement->id);
        $this->assertEquals($statement->payment_id, $payment->id);
        $this->assertNotNull($statement->paid_at);

        //  Make sure receipt was sent
        Mail::assertQueued(BillingReceipt::class, function($mail) use($statement){
            return $mail->statement->id === $statement->id;
        });

        //  Make sure unsuspension mail was sent
        Mail::assertQueued(AccountUnsuspended::class, function($mail){
            return $mail->user->id === $this->user->id;
        });

        $account = Account::find($this->account->id);
        $this->assertNull($account->suspended_at);
        $this->assertNull($account->suspension_code);
        $this->assertNull($account->suspension_message);

        $billing = Billing::find($this->billing->id);
        $this->assertEquals($billing->suspension_warnings, 0);
        $this->assertNull($billing->next_suspension_warning_at);

        //
        //  Make sure the alert is now missing
        //
        $this->assertDatabaseMissing('alerts', [
            'id' => $alert->id,
            'deleted_at' => null
        ]);
    }

    /**
     * Test paying a statement fails gracefully
     * 
     * @group billing
     */
    public function testPayStatementFailsGracefully()
    {
        //  Suspend
        $this->account->suspended_at        = now();
        $this->account->suspension_code     = Account::SUSPENSION_CODE_OUSTANDING_BALANCE;
        $this->account->suspension_message  = Account::SUSPENSION_CODE_OUSTANDING_BALANCE;
        $this->account->save();

        $this->billing->suspension_warnings = 3;
        $this->billing->next_suspension_warning_at = now();
        $this->billing->save();

        $paymentMethod = $this->createPaymentMethod();

        $this->mock(PaymentManager::class, function($mock){
            $mock->shouldReceive('charge')
                 ->once()
                 ->andReturn(null);
        });

        $statement = $this->createBillableStatement([
            'billing_id'               => $this->billing->id,
            'billing_period_starts_at' => now()->subDays(30)->startOfDay(),
            'billing_period_ends_at'   => now()->endOfDay()
        ]);

        $response = $this->json('POST', route('pay-statement', [
            'billingStatement' => $statement->id,
            'payment_method_id'=> $paymentMethod->id
        ]));

        $response->assertStatus(400);
    }
}
