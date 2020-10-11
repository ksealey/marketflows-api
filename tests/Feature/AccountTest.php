<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\Account;
use App\Models\Company;
use App\Models\Company\ScheduledExport;
use App\Models\Company\Report;
use App\Models\Company\Webhook;
use App\Models\Company\Contact;
use App\Models\Company\PhoneNumberConfig;
use App\Models\Company\KeywordTrackingPool;
use App\Models\Company\Call;
use App\Models\Company\CallRecording;
use App\Models\Company\AudioClip;
use App\Models\Company\PhoneNumber;
use App\Services\PhoneNumberService;
use Queue;
use Storage;

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
        $account = factory(Account::class)->make();
        $response = $this->json('PUT', route('update-account'), [
            'name'          => $account->name,
            'tts_language'  => $account->tts_language,
            'tts_voice'     => $account->tts_voice
        ]);
        $response->assertStatus(200);
        $response->assertJSON([
            'id'            => $this->account->id,
            'name'          => $account->name,
            'tts_language'  => $account->tts_language,
            'tts_voice'     => $account->tts_voice
        ]);

        $account = Account::find($this->account->id);

        $this->assertDatabaseHas('accounts', [
            'id' => $this->account->id,
            'name'          => $account->name,
            'tts_language'  => $account->tts_language,
            'tts_voice'     => $account->tts_voice
        ]);
    }

    /**
     * Test closing account fails with past due statement
     * 
     * @group accounts
     */
    public function testClosingAccountFailsForPastDueStatement()
    {
        $statement = $this->createBillableStatement([
            'billing_id'               => $this->billing->id,
            'billing_period_starts_at' => now()->subDays(30)->startOfDay(),
            'billing_period_ends_at'   => now()->endOfDay()
        ]);

        $response = $this->json('DELETE', route('delete-account', [
            'confirm_close' => 1
        ]));
        
        $response->assertStatus(400);
        $response->assertJSON([
            'error' => 'You must first pay all unpaid statements to close your account'
        ]);
    }

    /**
     * Test closing account removes resources
     * 
     * @group accounts
     */
    public function testClosingAccountRemovesResources()
    {
        Storage::fake();

        $company   = $this->createCompany();
        $statement = $this->createBillableStatement([
            'billing_id'               => $this->billing->id,
            'billing_period_starts_at' => now()->subDays(30)->startOfDay(),
            'billing_period_ends_at'   => now()->endOfDay(),
            'paid_at'                  => now()
        ]);

        $webhook = factory(Webhook::class)->create([
            'account_id' => $company->account_id,
            'company_id' => $company->id,
            'created_by' => $this->user->id
        ]);

        $audioClip = factory(AudioClip::class)->create([
            'account_id' => $company->account_id,
            'company_id' => $company->id,
            'path'       => '/accounts/' . $company->account_id . '/companies/' . $company->id .'/file.mp3',
            'created_by' => $this->user->id
        ]);
        Storage::put($audioClip->path, str_random(40));

        $config = factory(PhoneNumberConfig::class)->create([
            'account_id' => $company->account_id,
            'company_id' => $company->id,
            'created_by' => $this->user->id,
            'greeting_audio_clip_id' => $audioClip->id,
        ]);

        $keywordTrackingPool = factory(KeywordTrackingPool::class)->create([
            'account_id' => $company->account_id,
            'company_id' => $company->id,
            'created_by' => $this->user->id,
            'phone_number_config_id' => $config->id
        ]);

        $poolNumbers = factory(PhoneNumber::class, 5)->create([
            'account_id' => $company->account_id,
            'company_id' => $company->id,
            'created_by' => $this->user->id,
            'keyword_tracking_pool_id' => $keywordTrackingPool->id,
            'phone_number_config_id' => $config->id
        ]);

        $detachedNumbers = factory(PhoneNumber::class, 5)->create([
            'account_id' => $company->account_id,
            'company_id' => $company->id,
            'created_by' => $this->user->id,
            'phone_number_config_id' => $config->id
        ]);

        foreach($detachedNumbers as $phoneNumber){
            $contacts = factory(Contact::class, 2)->create([
                'account_id' => $company->account_id,
                'company_id' => $company->id,
            ])->each(function($contact) use($company, $phoneNumber){
                $call = factory(Call::class)->create([
                    'account_id'    => $company->account_id,
                    'company_id'    => $company->id,
                    'contact_id'    => $contact->id,
                    'phone_number_id'=> $phoneNumber->id,
                    'phone_number_name' => $phoneNumber->name
                ]);

                factory(CallRecording::class)->create([
                    'account_id'    => $company->account_id,
                    'company_id'    => $company->id,
                    'call_id'       => $call->id
                ]);

                Storage::put('/accounts/' . $company->account_id . '/companies/' . $company->id . '/' . $call->id . '.mp3', 'content');
            });
        }

        $report = factory(Report::class)->create([
            'account_id' => $company->account_id,
            'company_id' => $company->id,
            'created_by' => $this->user->id,
        ]);

        $scheduledExport = factory(ScheduledExport::class)->create([
            'company_id' => $company->id,
            'report_id'  => $report->id
        ]);
        
        //    
        //  Perform delete
        //
        $this->mock(PhoneNumberService::class, function($mock) use($company){
            $mock->shouldReceive('releaseNumber')
                 ->times(PhoneNumber::where('account_id', $this->account->id)->count());
        });
        $response = $this->json('DELETE', route('delete-account', [
            'confirm_close' => 1
        ]));
        $response->assertStatus(200);
        $response->assertJSON([
            'message' => 'Bye'
        ]);
        
        //
        //  Make sure the resources were removed
        //
        $this->assertDatabaseMissing('accounts', [
            'id' => $this->account->id,
            'deleted_at' => null
        ]);

        $this->assertDatabaseMissing('billing', [
            'account_id' => $this->account->id,
            'deleted_at' => null
        ]);

        $this->assertDatabaseMissing('companies', [
            'account_id'  => $this->account->id,
            'deleted_at' => null
        ]);

        $this->assertDatabaseMissing('webhooks', [
            'account_id' => $this->account->id,
            'deleted_at' => null
        ]);

        $this->assertDatabaseMissing('audio_clips', [
            'account_id'  => $this->account->id,
            'deleted_at'  => null
        ]);

        $this->assertDatabaseMissing('phone_number_configs', [
            'account_id' => $this->account->id,
            'deleted_at' => null
        ]);

        $this->assertDatabaseMissing('keyword_tracking_pools', [
            'account_id' => $this->account->id,
            'deleted_at' => null
        ]);

        foreach($poolNumbers as $phoneNumber){
            $this->assertDatabaseMissing('phone_numbers', [
                'account_id'  => $this->account->id,
                'deleted_at' => null
            ]);
        }

        foreach($detachedNumbers as $phoneNumber){
            $this->assertDatabaseMissing('phone_numbers', [
                'account_id'  => $this->account->id,
                'deleted_at' => null
            ]);
        }

        $this->assertDatabaseMissing('contacts', [
            'account_id'  => $this->account->id,
            'deleted_at' => null
        ]);

        $this->assertDatabaseMissing('calls', [
            'account_id'  => $this->account->id,
            'deleted_at' => null
        ]);

        $this->assertDatabaseMissing('call_recordings', [
            'account_id'  => $this->account->id,
            'deleted_at' => null
        ]);

        $this->assertDatabaseMissing('api_credentials', [
            'account_id'  => $this->account->id
        ]);

        $this->assertDatabaseMissing('users', [
            'account_id'  => $this->account->id,
            'deleted_at' => null
        ]);

        $this->assertDatabaseMissing('payment_methods', [
            'account_id'  => $this->account->id,
            'deleted_at' => null
        ]);

        $this->assertDatabaseMissing('blocked_phone_numbers', [
            'account_id'  => $this->account->id,
            'deleted_at' => null
        ]);

        $this->assertDatabaseMissing('blocked_calls', [
            'account_id'  => $this->account->id,
            'deleted_at' => null
        ]);

        $this->assertDatabaseMissing('alerts', [
            'account_id'  => $this->account->id,
            'deleted_at' => null
        ]);

        $this->assertDatabaseMissing('support_tickets', [
            'account_id'  => $this->account->id,
            'deleted_at' => null
        ]);

        $this->assertDatabaseMissing('support_ticket_comments', [
            'account_id'  => $this->account->id,
            'deleted_at' => null
        ]);

        $this->assertDatabaseMissing('support_ticket_attachments', [
            'account_id'  => $this->account->id,
            'deleted_at' => null
        ]);

        $this->assertDatabaseMissing('billing', [
            'account_id'  => $this->account->id,
            'deleted_at' => null
        ]);

        Storage::assertMissing('/accounts/' . $company->account_id);
    }
}
