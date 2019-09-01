<?php

/* @var $factory \Illuminate\Database\Eloquent\Factory */

use App\Model;
use Faker\Generator as Faker;

$factory->define(\App\Models\Company\PhoneNumber::class, function (Faker $faker) {
    return [
        'country_code'              => 1,
        'number'                    => substr($faker->e164PhoneNumber, -10),
        'name'                      => $faker->ein,
        'source'                    => 'TRK_SOURCE',
        'forward_to_country_code'   => 1,
        'forward_to_number'         => substr($faker->e164PhoneNumber, -10),
        'voice'                     => true,
        'sms'                       => true,
        'mms'                       => true,
        'external_id'                 => str_random(40)
    ];
});
