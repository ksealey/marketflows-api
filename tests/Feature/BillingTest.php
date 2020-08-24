<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Queue;
use App\Models\Account;
use App\Models\Billing;
use App\Jobs\BillAccountJob;
use App\Models\BillingStatement;
use App\Models\Payment;
use App\Models\PaymentMethod;
use Tests\TestCase;
use App\Mail\BillingReceipt;
use App\Mail\PaymentMethodFailed;
use Artisan;
use Mail;
use \Mockery;
use \PaymentManager;

class BillingTest extends TestCase
{
    use \Tests\CreatesAccount;

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

        $billing->billing_period_starts_at = now()->subDays(7);
        $billing->billing_period_ends_at   = now()->subMinutes(2);
        $billing->save();

        // populateUsage($companyCount, $localNumberCount, $tollFreeNumberCount, $localCallsPerNumber, $tollFreeCallsPerNumber)
        $companyCount           = 2;
        $localNumberCount       = 11;
        $tollFreeNumberCount    = 1;
        $localCallsPerNumber    = 10;
        $tollFreeCallsPerNumber = 10;

        $this->populateUsage($companyCount, $localNumberCount, $tollFreeNumberCount, $localCallsPerNumber, $tollFreeCallsPerNumber);
        
        $serviceQuantity  = $billing->quantity(Billing::ITEM_SERVICE);
        $serviceTotal     = $billing->total(Billing::ITEM_SERVICE, $serviceQuantity);
        $this->assertEquals($serviceQuantity, 1);
        $this->assertEquals($serviceTotal, Billing::COST_SERVICE);

        $localNumberQuantity    = $billing->quantity(Billing::ITEM_NUMBERS_LOCAL);
        $localNumberTotal       = $billing->total(Billing::ITEM_NUMBERS_LOCAL, $localNumberQuantity);
        $this->assertEquals($localNumberQuantity, $companyCount * $localNumberCount);
        $this->assertEquals($localNumberTotal, 12 * Billing::COST_NUMBERS_LOCAL); // tier of 10

        $tollFreeNumberQuantity = $billing->quantity(Billing::ITEM_NUMBERS_TOLL_FREE);
        $tollFreeNumberTotal    = $billing->total(Billing::ITEM_NUMBERS_TOLL_FREE, $tollFreeNumberQuantity);
        $this->assertEquals($tollFreeNumberQuantity, 2);
        $this->assertEquals($tollFreeNumberTotal, 2 * Billing::COST_NUMBERS_TOLL_FREE);

        $localMinutesQuantity   = $billing->quantity(Billing::ITEM_MINUTES_LOCAL);
        $localMinutesTotal      = $billing->total(Billing::ITEM_MINUTES_LOCAL, $localMinutesQuantity);
        $this->assertEquals($localMinutesQuantity, $companyCount * $localNumberCount * $localCallsPerNumber);
        $this->assertEquals($localMinutesTotal, 0);

        $tollFreeMinutesQuantity= $billing->quantity(Billing::ITEM_MINUTES_TOLL_FREE);
        $tollFreeMinutesTotal   = $billing->total(Billing::ITEM_MINUTES_TOLL_FREE, $tollFreeMinutesQuantity);
        $this->assertEquals($tollFreeMinutesQuantity, $companyCount * $tollFreeNumberCount * $tollFreeCallsPerNumber);
        $this->assertEquals($tollFreeMinutesTotal, ($companyCount * $tollFreeNumberCount * $tollFreeCallsPerNumber) * Billing::COST_MINUTES_TOLL_FREE);

        $transMinutesQuantity   = $billing->quantity(Billing::ITEM_MINUTES_TRANSCRIPTION);
        $transMinutesTotal      = $billing->total(Billing::ITEM_MINUTES_TRANSCRIPTION, $transMinutesQuantity);
        $this->assertEquals($transMinutesQuantity, $localMinutesQuantity + $tollFreeMinutesQuantity);
        $this->assertEquals($transMinutesTotal, ($localMinutesQuantity + $tollFreeMinutesQuantity) * Billing::COST_MINUTES_TRANSCRIPTION);

        $storageQuantity        = $billing->quantity(Billing::ITEM_STORAGE_GB);
        $storageTotal           = $billing->total(Billing::ITEM_STORAGE_GB, $storageQuantity);
        $this->assertEquals($storageQuantity, 240);
        $this->assertEquals($storageTotal, (240 - Billing::TIER_STORAGE_GB) * Billing::COST_STORAGE_GB);
    }
}
