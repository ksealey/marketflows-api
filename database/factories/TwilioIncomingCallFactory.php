<?php

/* @var $factory \Illuminate\Database\Eloquent\Factory */

use Faker\Generator as Faker;
use Tests\Models\TwilioIncomingCall;

$factory->define(TwilioIncomingCall::class, function (Faker $faker) {
    return [
        'AccountSid'    => config('services.twilio.sid'),
        'CallSid'       => str_random(40),
        'CallStatus'    => 'ringing',
        'Direction'     => 'inbound',
        'To'            => $faker->e164PhoneNumber,
        'ToCity'        => $faker->city,
        'ToState'       => $faker->state,
        'ToZip'         => $faker->postCode,
        'ToCountry'     => 'US',
        'From'          => $faker->e164PhoneNumber,
        'FromCity'      => $faker->city,
        'FromState'     => $faker->state,
        'FromZip'       => $faker->postCode,
        'FromCountry'   => 'US',
    ];
});