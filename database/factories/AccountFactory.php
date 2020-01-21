<?php

/* @var $factory \Illuminate\Database\Eloquent\Factory */

use App\Model;
use Faker\Generator as Faker;

$factory->define(\App\Models\Account::class, function (Faker $faker) {
    return [
        'name'      => $faker->company,
        'country'   => 'US',
        'balance'   => 0.00,
        'rates'     => json_encode([
            'PhoneNumber.Local'    => 4.00,
            'PhoneNumber.TollFree' => 5.00,
            'Call.In'              => 0.10,
            'Call.Out'             => 0.10,
            'Sms.In'               => 0.05,
            'Sms.Out'              => 0.05
        ]),
        'auto_reload_enabled_at' => date('Y-m-d H:i:s')
    ];
});
