<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\Model;
use Faker\Generator as Faker;

$factory->define(App\Models\Company\CompanyPlugin::class, function (Faker $faker) {
    return [
        'settings' => json_encode([
            'project' => $faker->company,
            'key'     => $faker->uuid,
            'secret'  => str_random(40)
        ])
    ];
});
