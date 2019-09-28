<?php

/* @var $factory \Illuminate\Database\Eloquent\Factory */

use App\Model;
use Faker\Generator as Faker;

$factory->define(\App\Models\Company\PhoneNumberConfig::class, function (Faker $faker) {
    return [
        'name'                      => $faker->ein(),
        'source'                    => 'TRK_SOURCE_POOL',
        'forward_to_country_code'   => 1,
        'forward_to_number'         => substr($faker->e164PhoneNumber, -10),
        'recording_enabled_at'      => date('Y-m-d H:i:s'),
        'whisper_message'           => 'Hello',  
        'whisper_language'          => 'en-gb',
        'whisper_voice'             => 'alice'
    ];
});
