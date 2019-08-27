<?php

/* @var $factory \Illuminate\Database\Eloquent\Factory */

use App\Model;
use Faker\Generator as Faker;

$factory->define(\App\Models\Company\PhoneNumberPool::class, function (Faker $faker) {
    return [
        'name'                      => $faker->ein,
        'source'                    => 'TRK_SOURCE_POOL',
        'forward_to_country_code'   => 1,
        'forward_to_number'         => substr($faker->e164PhoneNumber, -10),
    ];
});
