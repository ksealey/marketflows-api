<?php

/* @var $factory \Illuminate\Database\Eloquent\Factory */

use App\Model;
use Faker\Generator as Faker;

$factory->define(\App\Models\Company\PhoneNumberPool::class, function (Faker $faker) {
    return [
        'name'                      => $faker->ein,
        'auto_provision_enabled_at' => date('Y-m-d H:i:s')
    ];
});
