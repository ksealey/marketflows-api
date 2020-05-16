<?php

/* @var $factory \Illuminate\Database\Eloquent\Factory */

use App\Model;
use Faker\Generator as Faker;

$factory->define(\App\Models\Company\PhoneNumberConfig::class, function (Faker $faker) {
    return [
        'name'                      => $faker->ein(),
        'forward_to_number'         => substr($faker->e164PhoneNumber, -10),
        'greeting_enabled'          => 1,
        'greeting_message'          => 'Hello Greeting',
        'recording_enabled'         => 1,
        'whisper_message'           => 'Hello Whisper'
    ];
});
