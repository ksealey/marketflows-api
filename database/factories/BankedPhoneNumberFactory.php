<?php

/* @var $factory \Illuminate\Database\Eloquent\Factory */

use App\Model;
use Faker\Generator as Faker;
use \App\Models\Company\PhoneNumber;

$factory->define(\App\Models\BankedPhoneNumber::class, function (Faker $faker) {
    return [
        'external_id'  => str_random(30),
        'country'      => 'US',
        'country_code' => '1',
        'number'       => substr($faker->e164PhoneNumber, -10),
        'voice'        => 1,
        'sms'          => 1,
        'mms'          => 1,
        'type'         => mt_rand(0,1) ? PhoneNumber::TYPE_LOCAL : PhoneNumber::TYPE_TOLL_FREE,
        'calls'        => 0,
        'purchased_at' => now(),
        'release_by'   => now()->addDays(15),
        'status'       => 'Available',       
    ];
});
