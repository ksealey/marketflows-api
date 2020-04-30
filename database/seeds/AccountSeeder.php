<?php

use Illuminate\Database\Seeder;
use \App\Models\Account;
use \App\Models\User;

class AccountSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        factory(Account::class, 100)->create()->each(function ($account) {
            $userCount = mt_rand(1, 10);
            factory(User::class, $userCount)->create([
                'account_id' => $account->id
            ]);
        });
    }
}
