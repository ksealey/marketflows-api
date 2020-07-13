<?php
namespace Tests;

use \App\Models\Account;
use \App\Models\Company;
use \App\Models\User;
use \App\Models\Role;
use \App\Models\UserCompany;
use \App\Models\Company\PhoneNumber;
use \App\Models\Company\PhoneNumberConfig;
use \App\Models\Company\Campaign;

trait CreatesUser
{
    public $account;

    public $company;

    public $user;

    public function createUser(array $fields = [])
    {
        $this->account = factory(Account::class)->create();
        
        $this->user = factory(User::class)->create(array_merge([
            'account_id' => $this->account->id,
            'role_id'    => Role::createAdminRole($this->account)->id
        ], $fields));

        $this->company = factory(Company::class)->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id
        ]);

        UserCompany::create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id
        ]);

        return $this->user;
    }

    public function createPhoneNumber($fields = [], $config = null)
    {
        $user = $this->user ?: $this->createUser();

        if( ! $config )
            $config = $this->createPhoneNumberConfig();

        return factory(PhoneNumber::class)->create(array_merge([
            'phone_number_config_id' => $config->id,
            'company_id'             => $this->company->id,
            'user_id'             => $user->id,
        ], $fields));
    }

    public function createPhoneNumberConfig($fields = []){
        $user = $this->user ?: $this->createUser();

        return factory(PhoneNumberConfig::class)->create(array_merge([
            'company_id'  => $this->company->id,
            'user_id'  => $user->id
        ], $fields));
    }

    public function authHeaders(array $additionalHeaders = [])
    {
        return array_merge([
            'Authorization' => 'Bearer ' . $this->user->auth_token
        ], $additionalHeaders);
    }
}