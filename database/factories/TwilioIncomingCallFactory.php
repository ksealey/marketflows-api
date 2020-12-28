<?php

/* @var $factory \Illuminate\Database\Eloquent\Factory */

use Faker\Generator as Faker;
use Tests\Models\TwilioIncomingCall;
use \App\Models\Company\PhoneNumber;

$factory->define(TwilioIncomingCall::class, function (Faker $faker) {
    $firstName = $faker->firstName;
    $lastName  = $faker->lastName;
    $fromPhone     = $faker->e164PhoneNumber;
    $fromCountryCode = PhoneNumber::countryCode($fromPhone);
    $fromNumber      = PhoneNumber::number($fromPhone);
    $toPhone     = $faker->e164PhoneNumber;
    $toCountryCode = PhoneNumber::countryCode($toPhone);
    $toNumber      = PhoneNumber::number($toPhone);
    $fromCity        = $faker->city;
    $fromState      = $faker->stateAbbr;
    $fromZip        = $faker->postCode; 
    $toCity        = $faker->city;
    $toState      = $faker->stateAbbr;
    $toZip        = $faker->postCode;       

    return [
        'AccountSid'    => config('services.twilio.sid'),
        'CallSid'       => str_random(40),
        'CallerName'    => $lastName . ' ' . $firstName,
        'CallStatus'    => 'ringing',
        'Direction'     => 'inbound',
        'To'            => $toPhone,
        'ToCity'        => $toCity,
        'ToState'       => $toState,
        'ToZip'         => $toZip,
        'ToCountry'     => 'US',
        'From'          => $fromPhone,
        'FromCity'      => $fromCity,
        'FromState'     => $fromState,
        'FromZip'       => $fromZip,
        'FromCountry'   => 'US',
        'variables'  => json_encode([
            'caller_first_name'     => $firstName,
            'caller_last_name'      => $lastName,
            'caller_city'           => $fromCity,
            'caller_state'          => $fromState,
            'caller_zip'            => $fromZip,
            'caller_country'        => 'US',
            'caller_cc'             => $fromCountryCode,
            'caller_number'         => $fromNumber,
            'dialed_cc'             => $toCountryCode,
            'dialed_number'         => $toNumber,
            'company_name'          => '',
            'failed_attempts'       => 0,
            'remaining_attempts'    => 3,
        ]),
    ];
});