<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use \App\Models\Alert;
use \App\Models\Account;
use \App\Models\Billing;
use \App\Models\BillingStatement;
use \App\Models\BillingStatementItem;
use \App\Mail\SuspensionWarning3Days as SuspensionWarning3DaysMail;
use \App\Mail\SuspensionWarning7Days as SuspensionWarning7DaysMail;
use \App\Mail\SuspensionWarning9Days as SuspensionWarning9DaysMail;
use \App\Mail\SuspensionWarning10Days as SuspensionWarning10DaysMail;
use \App\Jobs\ReleaseAccountNumbersJob;
use \App\Services\PhoneNumberService;
use Artisan;
use Mail;
use Queue;

class AccountTest extends TestCase
{
    use \Tests\CreatesAccount, RefreshDatabase;

    /**
     * Test account suspension 3-day mail sent
     * 
     * @group accounts
     */
    public function testAccountSuspension3DaysMailSent()
    {
        Mail::fake();

        //
        //  Create a few statements that are not past due for other accounts
        //
        for( $i = 0; $i < 3; $i++ ){
            $account = factory(Account::class)->create();
            $billing = factory(Billing::class)->create([
                'account_id' => $account->id
            ]);
            $this->createBillableStatement([
                'billing_id'               => $billing->id,
                'billing_period_starts_at' => now()->subDays(30)->startOfDay(),
                'billing_period_ends_at'   => now()->endOfDay()
            ]);
        }

        //  Create a statement, 3 days past due
        $end   = now()->subDays(3);
        $start = (clone $end)->subDays(30);
        $statement1 = $this->createBillableStatement([
            'billing_period_starts_at' => $start,
            'billing_period_ends_at'   => $end
        ]);
        $statement2 = $this->createBillableStatement([
            'billing_period_starts_at' => $start,
            'billing_period_ends_at'   => $end
        ]);

        Artisan::call('send-account-suspension-warnings');

        Mail::assertQueued(SuspensionWarning3DaysMail::class, function($mail){
            return $mail->user->id === $this->user->id;
        });

        //  Make sure the warnings are incremented
        $this->assertDatabaseHas('billing', [
            'id'                  => $this->billing->id,
            'suspension_warnings' => 1
        ]);

        $this->assertDatabaseHas('alerts', [
            'user_id'  => $this->user->id,
            'category' => Alert::CATEGORY_PAYMENT,
            'title'    => 'Pending account suspension',
        ]);
    }

    /**
     * Test account suspension 7-day mail sent
     * 
     * @group accounts
     */
    public function testAccountSuspension7DaysMailSent()
    {
        Mail::fake();

        //
        //  Create a few statements that are not past due for other accounts
        //
        for( $i = 0; $i < 3; $i++ ){
            $account = factory(Account::class)->create();
            $billing = factory(Billing::class)->create([
                'account_id' => $account->id
            ]);
            $this->createBillableStatement([
                'billing_id'               => $billing->id,
                'billing_period_starts_at' => now()->subDays(30)->startOfDay(),
                'billing_period_ends_at'   => now()->endOfDay()
            ]);
        }

        //  Create a statement, 7 days past due
        $this->billing->suspension_warnings = 1;
        $this->billing->save();

        $end   = now()->subDays(7);
        $start = (clone $end)->subDays(30);
        $statement1 = $this->createBillableStatement([
            'billing_period_starts_at' => $start,
            'billing_period_ends_at'   => $end
        ]);

        Artisan::call('send-account-suspension-warnings');

        Mail::assertQueued(SuspensionWarning7DaysMail::class, 1);
        
        //  Make sure the warnings are incremented
        $this->assertDatabaseHas('billing', [
            'id'                  => $this->billing->id,
            'suspension_warnings' => 2
        ]);

        //  Make sure account is suspended
        $account = Account::find($this->account->id);
        $this->assertNotNull($account->suspended_at);
        $this->assertNotNull($account->suspension_message);
        $this->assertEquals($account->suspension_code, Account::SUSPENSION_CODE_OUSTANDING_BALANCE);

        $this->assertDatabaseHas('alerts', [
            'user_id' => $this->user->id,
            'category' => Alert::CATEGORY_PAYMENT,
            'title'    => 'Account suspended - numbers pending release',
        ]);
    }

    /**
     * Test account suspension 9-day mail sent
     * 
     * @group accounts
     */
    public function testAccountSuspension9DaysMailSent()
    {
        Mail::fake();

        //
        //  Create a few statements that are not past due for other accounts
        //
        for( $i = 0; $i < 3; $i++ ){
            $account = factory(Account::class)->create();
            $billing = factory(Billing::class)->create([
                'account_id' => $account->id
            ]);
            $this->createBillableStatement([
                'billing_id'               => $billing->id,
                'billing_period_starts_at' => now()->subDays(30)->startOfDay(),
                'billing_period_ends_at'   => now()->endOfDay()
            ]);
        }

        //  Create a statement, 9 days past due
        $this->billing->suspension_warnings = 2;
        $this->billing->save();

        $end   = now()->subDays(9);
        $start = (clone $end)->subDays(30);
        $statement1 = $this->createBillableStatement([
            'billing_period_starts_at' => $start,
            'billing_period_ends_at'   => $end
        ]);

        Artisan::call('send-account-suspension-warnings');

        Mail::assertQueued(SuspensionWarning9DaysMail::class, 1);
        
        //  Make sure the warnings are incremented
        $this->assertDatabaseHas('billing', [
            'id'                  => $this->billing->id,
            'suspension_warnings' => 3
        ]);

        $this->assertDatabaseHas('alerts', [
            'user_id'  => $this->user->id,
            'category' => Alert::CATEGORY_PAYMENT,
            'title'    => 'Account suspended - numbers pending release tomorrow',
        ]);
    }

    /**
     * Test account suspension 10-day mail sent and numbers released
     * 
     * @group accounts
     */
    public function testAccountSuspension10DaysMailSent()
    {
        Mail::fake();

        $company = $this->createCompany();
        $config  = $this->createConfig($company);
        $number  = $this->createPhoneNumber($company, $config);

        //
        //  Create a few statements that are not past due for other accounts
        //
        for( $i = 0; $i < 3; $i++ ){
            $account = factory(Account::class)->create();
            $billing = factory(Billing::class)->create([
                'account_id' => $account->id
            ]);
            $this->createBillableStatement([
                'billing_id'               => $billing->id,
                'billing_period_starts_at' => now()->subDays(30)->startOfDay(),
                'billing_period_ends_at'   => now()->endOfDay()
            ]);
        }

        //  Create a statement, 10 days past due
        $this->billing->suspension_warnings = 3;
        $this->billing->save();

        $end   = now()->subDays(10);
        $start = (clone $end)->subDays(30);
        $statement1 = $this->createBillableStatement([
            'billing_period_starts_at' => $start,
            'billing_period_ends_at'   => $end
        ]);

        $this->mock(PhoneNumberService::class, function($mock){
            $mock->shouldReceive('releaseNumber')
                 ->once();
        });

        Artisan::call('send-account-suspension-warnings');

        Mail::assertQueued(SuspensionWarning10DaysMail::class, 1);
        
        //  Make sure the warnings are incremented
        $this->assertDatabaseHas('billing', [
            'id'                  => $this->billing->id,
            'suspension_warnings' => 4
        ]);

        //  Make sure the number was deleted
        $this->assertDatabaseMissing('phone_numbers', [
            'id'         => $number->id,
            'deleted_at' => null
        ]);

        $this->assertDatabaseHas('alerts', [
            'user_id'  => $this->user->id,
            'category' => Alert::CATEGORY_PAYMENT,
            'title'    => 'Account suspended - phone numbers released',
        ]);
    }
}
