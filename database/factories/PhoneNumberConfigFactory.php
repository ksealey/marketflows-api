<?php

/* @var $factory \Illuminate\Database\Eloquent\Factory */

use App\Model;
use Faker\Generator as Faker;

$factory->define(\App\Models\Company\PhoneNumberConfig::class, function (Faker $faker) {
    return [
        'name'                      => $faker->ein(),
        'forward_to_number'         => substr($faker->e164PhoneNumber, -10),
        'greeting_enabled'          => 1,
        'greeting_message_type'     => 'TEXT',
        'greeting_message'          => $faker->realText(64),

        'keypress_enabled'          => 1,
        'keypress_key'              => 1,
        'keypress_attempts'         => 3,
        'keypress_timeout'          => 30,
        'keypress_message_type'     => 'TEXT',
        'keypress_message'          => $faker->realText(64),

        'whisper_enabled'           => 1,
        'whisper_message'           => $faker->realText(64),

        'recording_enabled'         => 1,

        'transcription_enabled'      => 1,

        'keypress_conversion_enabled'           => 1,
        'keypress_conversion_key_converted'     => 1,
        'keypress_conversion_key_unconverted'   => 2,
        'keypress_conversion_attempts'          => 3,
        'keypress_conversion_timeout'           => 10,
        'keypress_conversion_directions_message'=> $faker->realText(50),
        'keypress_conversion_error_message'     => $faker->realText(50),
        'keypress_conversion_success_message'   => $faker->realText(50),
        'keypress_conversion_failure_message'   => $faker->realText(50),

        'keypress_qualification_enabled'        => 1,
        'keypress_qualification_key_qualified'  => 2,
        'keypress_qualification_key_potential'  => 3,
        'keypress_qualification_key_customer'   => 4,
        'keypress_qualification_key_unqualified'  => 5,
        'keypress_qualification_attempts'  => 5,
        'keypress_qualification_timeout'   => 20,
        'keypress_qualification_directions_message'  => $faker->realText(50),
        'keypress_qualification_error_message'  => $faker->realText(50),
        'keypress_qualification_success_message'  => $faker->realText(50),
        'keypress_qualification_failure_message'  => $faker->realText(50),

    ];
});
