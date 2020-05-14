<?php

/* @var $factory \Illuminate\Database\Eloquent\Factory */

use Faker\Generator as Faker;
use Tests\Models\TwilioPhoneNumber;

$factory->define(TwilioPhoneNumber::class, function (Faker $faker) {
    return [
        'sid'                   => str_random(40),
        'phoneNumber'           => $faker->e164PhoneNumber,
        'capabilities'          => [
            'voice' => 1,
            'mms'   => 1,
            'sms'   => 1
        ]
    ];
});