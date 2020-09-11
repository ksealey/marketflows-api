<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\Model;
use Faker\Generator as Faker;
use App\Models\Company\ScheduledExport;
$factory->define(ScheduledExport::class, function (Faker $faker) {
    return [
        'day_of_week'               => mt_rand(0,6),
        'hour_of_day'               => mt_rand(0, 23),
        'timezone'                  => $faker->timezone,
        'delivery_method'           => 'email',
        'delivery_email_addresses'  => $faker->email . ',' . $faker->email
    ];
});
