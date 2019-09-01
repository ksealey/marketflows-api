<?php

/* @var $factory \Illuminate\Database\Eloquent\Factory */

use App\Model;
use Faker\Generator as Faker;

$factory->define(\Tests\Models\TwilioCall::class, function (Faker $faker) {
    return [
        'CallSid'       => str_random(40),
        'CallStatus'    => 'ringing',
        'To'            => $faker->e164PhoneNumber,
        'ToCity'        => $faker->city,
        'ToCountry'     => 'US',
        'ToState'       => $faker->stateAbbr,
        'ToZip'         => $faker->postcode,
        'From'          => $faker->e164PhoneNumber,
        'FromCity'      => $faker->city,
        'FromCountry'   => 'US',
        'FromState'     => $faker->stateAbbr,
        'FromZip'       => $faker->postcode,
        'Direction'     => 'inbound'
    ];
});
