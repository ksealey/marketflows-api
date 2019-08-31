<?php
namespace Tests;

use \App\Models\Account;
use \App\Models\Company;
use \App\Models\User;
use \App\Models\UserCompany;
use \App\Models\Company\Campaign;

trait CreatesUser
{
    public $account;

    public $company;

    public $user;

    public function createUser(array $fields = [])
    {
        $this->account = factory(Account::class)->create();
        $this->company = factory(Company::class)->create([
            'account_id' => $this->account->id
        ]);
        $this->user =  factory(User::class)->create(array_merge([
            'account_id' => $this->account->id,
            'company_id' => $this->company->id,
            'is_admin'   => true
        ], $fields));

        UserCompany::create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id
        ]);

        return $this->user;
    }

    public function createCampaign($fields = [])
    {
        $user = $this->user ?: $this->createUser();
        
        return factory(Campaign::class)->create(array_merge([
            'company_id' => $this->company->id,
            'created_by' => $user->id,
            'type'       => Campaign::TYPE_PRINT
        ], $fields));
    }

    public function authHeaders(array $additionalHeaders = [])
    {
        return array_merge([
            'Authorization' => 'Bearer ' . $this->user->auth_token
        ], $additionalHeaders);
    }
}