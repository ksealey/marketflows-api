<?php

namespace Tests;

use \App\Models\Account;
use \App\Models\User;

trait CreatesAccount
{
    public $account;
    public $user;

    public function setUp() : void
    {
        parent::setUp();

        $this->account = factory(Account::class)->create();
        $this->user    = factory(User::class)->create([
            'account_id'     => $this->account->id
        ]);
        
    }

    public function json($method, $route, $body = [], $headers = [])
    {
        $headers = array_merge([
            'Authorization' => 'Bearer ' . $this->user->auth_token
        ], $headers);

        return parent::json($method, $route, $body, $headers);
    }
}