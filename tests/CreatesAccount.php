<?php

namespace Tests;

use \App\Models\Account;
use \App\Models\Billing;
use \App\Models\User;
use App\Models\Company;
use \App\Models\Company\AudioClip;
use \App\Models\Company\Report;
use \App\Models\Company\ReportAutomation;
use App\Models\Company\PhoneNumberConfig;
use App\Models\Company\PhoneNumber;
use App\Models\Company\PhoneNumberPool;
use App\Models\Company\Call;
use App\Models\Company\CallRecording;
use \App\Models\BlockedPhoneNumber;
use \App\Models\BlockedPhoneNumber\BlockedCall;
use Storage;

trait CreatesAccount
{
    public $account;
    public $billing;
    public $user;

    public function setUp() : void
    {
        parent::setUp();

        $this->account = factory(Account::class)->create();

        $this->billing = factory(Billing::class)->create([
            'account_id' => $this->account->id
        ]);

        $this->user = factory(User::class)->create([
            'account_id' => $this->account->id
        ]);
    }

    public function createCompany($with = [])
    {
        return factory(Company::class)->create(array_merge([
            'account_id' => $this->user->account_id,
            'created_by' => $this->user->id
        ], $with));
    }

    public function createCompanies()
    {
        Storage::fake();
        
        //  Company
        $company = factory(Company::class)->create([
            'account_id' => $this->user->account_id,
            'created_by' => $this->user->id
        ]);

        //  Audio Clip
        $audioClip  = factory(AudioClip::class)->create([
            'account_id' => $company->account_id,
            'company_id' => $company->id,
            'created_by' => $this->user->id
        ]);
        Storage::put($audioClip->path, 'foobar');

        //  Config
        $config = factory(PhoneNumberConfig::class)->create([
            'account_id' => $company->account_id,
            'company_id' => $company->id,
            'created_by' => $this->user->id,
            'greeting_audio_clip_id' => $audioClip->id,
        ]);

        //  Some numbers
        $phoneNumber = factory(PhoneNumber::class)->create([
            'account_id' => $this->user->account_id,
            'company_id' => $company->id,
            'created_by' => $this->user->id, 
            'phone_number_config_id' => $config->id
        ]);
        $phoneNumber->each(function($phoneNumber){
            factory(Call::class, 2)->create([
                'account_id' => $phoneNumber->account_id,
                'company_id' => $phoneNumber->company_id,
                'phone_number_id' =>$phoneNumber->id
            ])->each(function($call){
                factory(CallRecording::class, 2)->create([
                    'call_id' => $call->id
                ]);
            });
        });

        //  A number pool
        $pool = factory(PhoneNumberPool::class)->create([
            'account_id' => $company->account_id,
            'company_id' => $company->id,
            'created_by' => $this->user->id, 
            'phone_number_config_id' => $config->id
        ]);
        
        $past = now()->subMonths(1)->addDays(4);
        $poolNumbers = factory(PhoneNumber::class, 2)->create([
            'account_id' => $this->user->account_id,
            'company_id' => $company->id,
            'created_by' => $this->user->id, 
            'phone_number_config_id' => $pool->phone_number_config_id,
            'phone_number_pool_id' => $pool->id,
            'purchased_at'         => $past // Should be released
        ]);
        $poolNumbers->each(function($phoneNumber){
            factory(Call::class, 2)->create([
                'account_id' => $phoneNumber->account_id,
                'company_id' => $phoneNumber->company_id,
                'phone_number_id' =>$phoneNumber->id
            ]);
        });

        //  Blocked Numbers
        factory(BlockedPhoneNumber::class, 2)->create([
            'account_id' => $this->account->id,
            'company_id' => $company->id,
            'created_by' => $this->user->id
        ])->each(function($blockedNumber) use($phoneNumber){
            factory(BlockedCall::class, 2)->create([
                'blocked_phone_number_id' => $blockedNumber->id,
                'phone_number_id'         => $phoneNumber->id,
            ]);
        });

        //  Report
        $report = factory(Report::class)->create([
            'company_id' => $company->id,
            'created_by' => $this->user->id
        ]);

        //  Report Automation
        $automations = factory(ReportAutomation::class)->create([
            'report_id'  => $report->id
        ]);

        return [
            'company' => $company,
            'audio_clip' => $audioClip,
            'phone_number_config' => $config,
            'phone_number' => $phoneNumber,
            'report' => $report,
            'phone_number_pool' => $pool,
            'phone_number_pool_numbers' => $poolNumbers
        ];
    }

    public function json($method, $route, $body = [], $headers = [])
    {
        $headers = array_merge([
            'Authorization' => 'Bearer ' . $this->user->auth_token
        ], $headers);

        return parent::json($method, $route, $body, $headers);
    }
}