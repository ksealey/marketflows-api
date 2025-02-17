<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Queue;
use \Stripe\Stripe;
use \Stripe\Customer;
use App\Models\Plugin;
use App\Models\Alert;
use App\Models\Account;
use App\Models\Billing;
use App\Models\BillingStatement;
use App\Models\BillingStatementItem;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Company\PhoneNumber;
use App\Models\Company\CallRecording;
use App\Models\Company\CompanyPlugin;
use App\Mail\BillingReceipt;
use App\Mail\PaymentMethodFailed;
use App\Mail\AccountUnsuspended;
use App\Mail\NoPaymentMethodFound;
use App\Helpers\PaymentManager;
use App\Jobs\PayStatementJob;
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
     * Test creating statements
     * 
     * @group billing
     */
    public function testBillingStatementsAreCreated()
    {
        Queue::fake();

        $this->billing->billing_period_starts_at = now()->subDays(7);
        $this->billing->billing_period_ends_at   = now()->subMinutes(2);
        $this->billing->save();

        Artisan::call('create-statements');

        $statementCount = BillingStatement::where('billing_id', $this->billing->id)
                                      ->whereNull('paid_at')
                                      ->count();
        $this->assertEquals($statementCount, 1);
    }

    /**
     * Test creating statements with plugins
     * 
     * @group billing
     */
    public function testBillingStatementsAreCreatedWithPlugins()
    {
        Queue::fake();

        $this->billing->billing_period_starts_at = now()->subDays(7);
        $this->billing->billing_period_ends_at   = now()->subMinutes(2);
        $this->billing->save();

         //  Install a few plugins
         $company    = $this->createCompany();
         $available  = factory(Plugin::class,2)->create();
         $installed  = factory(Plugin::class)->create();
         $companyPlugin = factory(CompanyPlugin::class)->create([
             'company_id' => $company->id,
             'plugin_key' => $installed->key,
             'enabled_at' => now()->format('Y-m-d H:i:s')
         ]);

        Artisan::call('create-statements');

        $statementCount = BillingStatement::where('billing_id', $this->billing->id)
                                      ->whereNull('paid_at')
                                      ->count();

        $this->assertDatabaseHas('billing_statements', [
            'billing_id' => $this->billing->id,
            'paid_at'    => null
        ]);

        $this->assertDatabaseHas('billing_statement_items', [
            'label' => $companyPlugin->label . ' (Company ' . $company->id .')',
        ]);
    }

    /**
     * Test paying statements successfully
     * 
     * @group billing
     */
    public function testBillingStatementsArePaid()
    {
        $this->account->suspended_at                = now();
        $this->account->next_suspension_warning_at  = now();
        $this->account->suspension_warnings         = 3;
        $this->account->suspension_code             = Account::SUSPENSION_CODE_OUSTANDING_BALANCE;
        $this->account->suspension_message          = Account::SUSPENSION_CODE_OUSTANDING_BALANCE;
        $this->account->save();

        $alert = Alert::create([
            'account_id'    => $this->account->id,
            'user_id'       => $this->user->id,  
            'category'      => Alert::CATEGORY_PAYMENT,
            'type'          => Alert::TYPE_DANGER,
            'title'         => 'No payment method found',
            'message'       => 'No payment method was found on your account. Please add a payment method to avoid any disruptions in service.',
        ]);

        BillingStatementItem::where('id', '>', 0)->delete();
        BillingStatement::where('id', '>', 0)->delete();
        Mail::fake();

        $statement = $this->createBillableStatement();
        $intent    = (object)[
            'id'            => str_random(20),
            'client_secret' => str_random(20),
            'status'        => 'succeeded'
        ];

        $this->partialMock(PaymentManager::class, function($mock) use($statement, $intent){
            $mock->shouldReceive('createPaymentIntent')
                 ->with($this->billing->external_id, $this->paymentMethod->external_id, $statement->total, 'MarketFlows, LLC')
                 ->andReturn($intent);
        });

        Artisan::call('pay-statements');
        Artisan::call('pay-statements');
        Artisan::call('pay-statements');

        $statement = BillingStatement::find($statement->id);

        $this->assertNotNull($statement->paid_at);
        $this->assertEquals($statement->payment_attempts, 1);
        $this->assertNull($statement->next_payment_attempt_at);

        $this->assertDatabaseHas('payments', [
            'billing_statement_id' => $statement->id,
            'payment_method_id'    => $this->paymentMethod->id,
            'total'                => $statement->total
        ]);

        $this->assertDatabaseHas('accounts', [
            'id'                            => $this->account->id,
            'suspended_at'                  => null,
            'suspension_code'               => null,
            'suspension_message'            => null,
            'suspension_warnings'           => 0,
            'next_suspension_warning_at'    => null
        ]);

        $this->assertDatabaseMissing('alerts', [
            'id'         => $alert->id,
            'deleted_at' => null
        ]);

        Mail::assertQueued(BillingReceipt::class, 1);
        Mail::assertQueued(BillingReceipt::class, function($mail){
            return $mail->hasTo($this->user->email);
        });
    }

    /**
     * Test paying a statement without a payment method fails gracefully
     * 
     * @group billing
     */
    public function testBillingStatementsFailGracefullyWithoutPaymentMethod()
    {
        BillingStatementItem::where('id', '>', 0)->delete();
        BillingStatement::where('id', '>', 0)->delete();
        
        Mail::fake();

        $this->paymentMethod->delete();

        $statement = $this->createBillableStatement();
        $intent    = (object)[
            'id'            => str_random(20),
            'client_secret' => str_random(20),
            'status'        => 'succeeded'
        ];

        Artisan::call('pay-statements');
        Artisan::call('pay-statements');
        Artisan::call('pay-statements');

        $statement = BillingStatement::find($statement->id);

        $this->assertNull($statement->intent_id);
        $this->assertNull($statement->paid_id);
        $this->assertNotNull($statement->next_payment_attempt_at);

        $this->assertDatabaseMissing('payments', [
            'billing_statement_id' => $statement->id
        ]);
       
        Mail::assertSent(NoPaymentMethodFound::class, 1);
        Mail::assertSent(NoPaymentMethodFound::class, function($mail){
            return $mail->hasTo($this->user->email);
        });
    }

    /**
     * Test paying statements fails gracefully
     * 
     * @group billing
     */
    public function testBillingStatementsFailGracefully()
    {
        BillingStatementItem::where('id', '>', 0)->delete();
        BillingStatement::where('id', '>', 0)->delete();
        
        Mail::fake();

        Stripe::setApiKey(config('services.stripe.secret'));
        $customer = Customer::create([
            'description' => str_random(40)
        ]);

        $this->billing->external_id = $customer->id;
        $this->billing->save();

        $this->paymentMethod->external_id = 'pm_card_authenticationRequired';
        $this->paymentMethod->save();

        $statement = $this->createBillableStatement();
        $intent    = (object)[
            'id'            => str_random(20),
            'client_secret' => str_random(20),
            'status'        => 'succeeded'
        ];

        Artisan::call('pay-statements');
        Artisan::call('pay-statements');
        Artisan::call('pay-statements');

        $statement = BillingStatement::find($statement->id);
        $this->assertNotNull($statement->intent_id);
        $this->assertNull($statement->paid_id);
        $this->assertNotNull($statement->next_payment_attempt_at);

        $this->assertDatabaseMissing('payments', [
            'billing_statement_id' => $statement->id,
            'payment_method_id'    => $this->paymentMethod->id
        ]);
       
        Mail::assertQueued(PaymentMethodFailed::class, function($mail){
            return $mail->hasTo($this->user->email);
        });
        Mail::assertQueued(PaymentMethodFailed::class, 1);
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

        $companyCount           = 2;
        $localNumberCount       = 11;
        $tollFreeNumberCount    = 1;
        $localCallsPerNumber    = 10;
        $tollFreeCallsPerNumber = 10;
        $expectedStorageGB      = 240; //   @ 2 companies * 12 numbers * 10 calls per number with 1GB each call

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
        $this->assertEquals($storageQuantity, $expectedStorageGB);
        $this->assertEquals($storageTotal, ($expectedStorageGB - Billing::TIER_STORAGE_GB) * Billing::COST_STORAGE_GB);

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
     * Test that the billing calculates properly for beta
     * 
     * @group billing
     */
    public function testBillingCalculatesProperlyForBeta()
    {
        $this->account->is_beta = 1;
        $this->account->save();

        $billing = $this->billing;

        $billing->billing_period_starts_at = now()->subDays(7)->startOfDay();
        $billing->billing_period_ends_at   = now()->endOfDay();
        $billing->save();

        $billingPeriodStart = $billing->billing_period_starts_at;
        $billingPeriodEnd   = $billing->billing_period_ends_at;

        $companyCount           = 2;
        $localNumberCount       = 11;
        $tollFreeNumberCount    = 1;
        $localCallsPerNumber    = 10;
        $tollFreeCallsPerNumber = 10;
        $expectedStorageGB      = 240; //   @ 2 companies * 12 numbers * 10 calls per number with 1GB each call

        $this->populateUsage($companyCount, $localNumberCount, $tollFreeNumberCount, $localCallsPerNumber, $tollFreeCallsPerNumber);
        
        $serviceQuantity  = $billing->quantity(Billing::ITEM_SERVICE, $billingPeriodStart, $billingPeriodEnd);
        $serviceTotal     = $billing->total(Billing::ITEM_SERVICE, $serviceQuantity);
        $this->assertEquals($serviceQuantity, 1);
        $this->assertEquals($serviceTotal, Billing::COST_SERVICE_BETA);

        $localNumberQuantity    = $billing->quantity(Billing::ITEM_NUMBERS_LOCAL, $billingPeriodStart, $billingPeriodEnd);
        $localNumberTotal       = $billing->total(Billing::ITEM_NUMBERS_LOCAL, $localNumberQuantity);
        $this->assertEquals($localNumberQuantity, $companyCount * $localNumberCount);
        $this->assertEquals($localNumberTotal, 12 * Billing::COST_NUMBERS_LOCAL_BETA); // tier of 10

        $tollFreeNumberQuantity = $billing->quantity(Billing::ITEM_NUMBERS_TOLL_FREE, $billingPeriodStart, $billingPeriodEnd);
        $tollFreeNumberTotal    = $billing->total(Billing::ITEM_NUMBERS_TOLL_FREE, $tollFreeNumberQuantity);
        $this->assertEquals($tollFreeNumberQuantity, 2);
        $this->assertEquals($tollFreeNumberTotal, 2 * Billing::COST_NUMBERS_TOLL_FREE_BETA);

        $localMinutesQuantity   = $billing->quantity(Billing::ITEM_MINUTES_LOCAL, $billingPeriodStart, $billingPeriodEnd);
        $localMinutesTotal      = $billing->total(Billing::ITEM_MINUTES_LOCAL, $localMinutesQuantity);
        $this->assertEquals($localMinutesQuantity, $companyCount * $localNumberCount * $localCallsPerNumber);
        $this->assertEquals($localMinutesTotal, 0);

        $tollFreeMinutesQuantity= $billing->quantity(Billing::ITEM_MINUTES_TOLL_FREE, $billingPeriodStart, $billingPeriodEnd);
        $tollFreeMinutesTotal   = $billing->total(Billing::ITEM_MINUTES_TOLL_FREE, $tollFreeMinutesQuantity);
        $this->assertEquals($tollFreeMinutesQuantity, $companyCount * $tollFreeNumberCount * $tollFreeCallsPerNumber);
        $this->assertEquals($tollFreeMinutesTotal, ($companyCount * $tollFreeNumberCount * $tollFreeCallsPerNumber) * Billing::COST_MINUTES_TOLL_FREE_BETA);

        $transMinutesQuantity   = $billing->quantity(Billing::ITEM_MINUTES_TRANSCRIPTION, $billingPeriodStart, $billingPeriodEnd);
        $transMinutesTotal      = $billing->total(Billing::ITEM_MINUTES_TRANSCRIPTION, $transMinutesQuantity);
        $this->assertEquals($transMinutesQuantity, $localMinutesQuantity + $tollFreeMinutesQuantity);
        $this->assertEquals($transMinutesTotal, ($localMinutesQuantity + $tollFreeMinutesQuantity) * Billing::COST_MINUTES_TRANSCRIPTION_BETA);

        $storageQuantity        = $billing->quantity(Billing::ITEM_STORAGE_GB, $billingPeriodStart, $billingPeriodEnd);
        $storageTotal           = $billing->total(Billing::ITEM_STORAGE_GB, $storageQuantity);
        $this->assertEquals($storageQuantity, $expectedStorageGB);
        $this->assertEquals($storageTotal, ($expectedStorageGB - Billing::TIER_STORAGE_GB) * Billing::COST_STORAGE_GB_BETA);

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
     * Test paying a statement
     * 
     * @group billing
     */
    public function testPayStatement()
    {
        Mail::fake();

        //  Suspend
        $this->account->suspended_at        = now();
        $this->account->suspension_warnings = 3;
        $this->account->next_suspension_warning_at = now();
        $this->account->suspension_code     = Account::SUSPENSION_CODE_OUSTANDING_BALANCE;
        $this->account->suspension_message  = Account::SUSPENSION_CODE_OUSTANDING_BALANCE;
        $this->account->save();

        //  Add alert
        $alert = factory(Alert::class)->create([
            'account_id' => $this->account->id,
            'user_id'  => $this->user->id,
            'category' => Alert::CATEGORY_PAYMENT
        ]);

        $paymentMethod = $this->createPaymentMethod();

        $statement = $this->createBillableStatement([
            'billing_id'               => $this->billing->id,
            'billing_period_starts_at' => now()->subDays(30)->startOfDay(),
            'billing_period_ends_at'   => now()->endOfDay()
        ]);
        
        $this->partialMock(PaymentManager::class, function($mock){
            $mock->shouldReceive('createPaymentIntent')
                 ->once()
                 ->andReturn((object)[
                    'id' => str_random(20),
                    'client_secret' => str_random(20)
                 ]);
        });

        $response = $this->json('POST', route('pay-statement', [
            'billingStatement' => $statement->id,
        ]), [
            'payment_method_id'=> $paymentMethod->id
        ]);
        
        $response->assertStatus(201);
        $response->assertJSON([
            'kind' => 'Payment',
            'billing_statement_id' => $statement->id,
            'payment_method_id'    => $paymentMethod->id
        ]);

        //  Make sure statement is paid
        $statement = BillingStatement::find($statement->id);
    
        $this->assertEquals($statement->id, $response['billing_statement_id']);
        $this->assertNotNull($statement->paid_at);
        $this->assertNull($statement->next_payment_attempt_at);

        $this->assertDatabaseHas('payments', [
            'payment_method_id'    => $paymentMethod->id,
            'billing_statement_id' => $statement->id,
            'total'                => $statement->total
        ]);

        //  Make sure receipt was sent
        Mail::assertQueued(BillingReceipt::class, function($mail) use($statement){
            return $mail->statement->id === $statement->id;
        });

        //  Make sure unsuspension mail was sent
        Mail::assertQueued(AccountUnsuspended::class, function($mail){
            return $mail->user->id === $this->user->id;
        });

        $this->assertDatabaseHas('accounts', [
            'id'                            => $this->account->id,
            'suspended_at'                  => null,
            'suspension_code'               => null,
            'suspension_message'            => null,
            'suspension_warnings'           => 0,
            'next_suspension_warning_at'    => null
        ]);

        //
        //  Make sure the alert is now missing
        //
        $this->assertDatabaseMissing('alerts', [
            'id' => $alert->id,
            'deleted_at' => null
        ]);
    }

    /**
     * Test fetching current total
     * 
     * @group billing
     */
    public function testFetchingCurrentTotal()
    {
        CallRecording::where('id', '>', 0)->forceDelete();
         
        $companyCount           = 2;
        $localNumberCount       = 11; // 22/12 ($30)
        $tollFreeNumberCount    = 1; // 2/2 ($8)
        $localCallsPerNumber    = 10; // 220/0 ($0)
        $tollFreeCallsPerNumber = 10;// 20/20 ($1.40) 
        // Transcriptions 240/240 ($7.20)
        //  Storage 2400MB(2.34G)/2400MB(1.34G) (0.13)

        $this->populateUsage($companyCount, $localNumberCount, $tollFreeNumberCount, $localCallsPerNumber, $tollFreeCallsPerNumber);
        
        $response = $this->json('GET', route('current-billing'));
        $response->assertStatus(200);
        $response->assertJSON([
            'total' => $this->billing->currentTotal(),
            'items' => []
        ]);
    }

    /**
     * Test fetching current billing
     * 
     * @group billing
     */
    public function testFetchingBilling()
    {
        $loops      = mt_rand(1,4);
        $statements = [];
        $pastDue    = 0;
        for( $i = 0; $i < $loops; $i++){
            $statement = $this->createBillableStatement([
                'billing_id'               => $this->billing->id,
                'billing_period_starts_at' => now()->subDays(30)->startOfDay(),
                'billing_period_ends_at'   => now()->endOfDay()
            ]);

            $pastDue += $statement->total;
        }

        $response = $this->json('GET', route('read-billing'));
        $response->assertStatus(200);
        $response->assertJSON([
            'billing_period_starts_at' => $this->billing->billing_period_starts_at,
            'billing_period_ends_at'   => $this->billing->billing_period_ends_at,
            'past_due'                 => round($pastDue, 2)
        ]);
        
    }
}
