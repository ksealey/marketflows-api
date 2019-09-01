<?php

/* @var $factory \Illuminate\Database\Eloquent\Factory */

use App\Model;
use Faker\Generator as Faker;

$factory->define(\Tests\Models\TwilioCall::class, function (Faker $faker) {
    return [
        'CallSid' => str_random(40),
        'Called'  => $faker->e164PhoneNumber,
        'Caller'  => $faker->e164PhoneNumber,
        'CallStatus' => 'ringing'
    ];
});
