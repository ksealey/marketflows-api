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
        'keypress_message_type'     => 'TEXT',
        'keypress_message'          => $faker->realText(64),

        'whisper_enabled'           => 1,
        'whisper_message'           => $faker->realText(64),

        'recording_enabled'         => 1,

        'transcription_enabled'      => 1,
    ];
});
