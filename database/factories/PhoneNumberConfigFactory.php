<?php

/* @var $factory \Illuminate\Database\Eloquent\Factory */

use App\Model;
use Faker\Generator as Faker;

$factory->define(\App\Models\Company\PhoneNumberConfig::class, function (Faker $faker) {
    return [
        'name'                      => $faker->ein(),
        'forward_to_number'         => substr($faker->e164PhoneNumber, -10),
        'recording_enabled_at'      => date('Y-m-d H:i:s'),
        'whisper_message'           => 'Hello'
    ];
});
