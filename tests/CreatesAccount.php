<?php

namespace Tests;

use \App\Models\Account;
use \App\Models\Billing;
use \App\Models\User;
use App\Models\Company;
use App\Models\Company\PhoneNumberConfig;
use App\Models\Company\PhoneNumber;
use App\Models\Company\PhoneNumberPool;
use App\Models\Company\Call;

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

    public function createCompanies()
    {

        $companies = factory(Company::class, 5)->create([
            'account_id' => $this->account->id,
            'created_by' => $this->user->id
        ])
        ->each(function($company){
            //
            //  Create phone number configs
            //
            $phoneNumberConfigs = factory(PhoneNumberConfig::class, 2)->create([
                'company_id' => $company->id,
                'created_by' => $this->user->id
            ])->each(function($config){
                //  Create Phone Numbers
                $phoneNumbers = factory(PhoneNumber::class, mt_rand(1,2))->create([
                    'company_id' => $config->company_id,
                    'created_by' => $this->user->id,
                    'phone_number_config_id' => $config->id
                ])->each(function($phoneNumber){
                    factory(Call::class, 4)->create([
                        'account_id'      => $this->account->id,
                        'company_id'      => $phoneNumber->company_id,
                        'phone_number_id' => $phoneNumber->id
                    ]);
                });
            });

            //  
            //  Create phone number pool
            //
            $config = $phoneNumberConfigs[0];
            $phoneNumberPool = factory(PhoneNumberPool::class, 1)->create([
                'company_id' => $company->id,
                'created_by' => $this->user->id,
                'phone_number_config_id' => $config->id
            ])->each(function($pool) use($config){
                //  Create numbers for phone number pool
                factory(PhoneNumber::class, mt_rand(1,2))->create([
                    'company_id' => $config->company_id,
                    'created_by' => $this->user->id,
                    'phone_number_config_id' => $config->id,
                    'phone_number_pool_id' => $pool->id
                ])->each(function($phoneNumber){
                    factory(Call::class, 4)->create([
                        'account_id'      => $this->account->id,
                        'company_id'      => $phoneNumber->company_id,
                        'phone_number_id' => $phoneNumber->id
                    ]);
                });
            });
        }); 

        return $companies;
    }

    public function json($method, $route, $body = [], $headers = [])
    {
        $headers = array_merge([
            'Authorization' => 'Bearer ' . $this->user->auth_token
        ], $headers);

        return parent::json($method, $route, $body, $headers);
    }
}