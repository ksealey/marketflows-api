<?php

use Illuminate\Database\Seeder;

class AccountSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        factory(App\Models\Account::class, 100)->create()->each(function ($account) {
            $userCount = mt_rand(1, 10);
            factory(App\Models\User::class, $userCount)->create([
                'account_id' => $account->id
            ]);
        });
    }
}
