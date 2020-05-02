<?php

/* @var $factory \Illuminate\Database\Eloquent\Factory */

use App\Model;
use Faker\Generator as Faker;

$factory->define(\App\Models\Company\ReportAutomation::class, function (Faker $faker) {
    return [
        'type'              => 'EMAIL',
        'email_addresses'   => [$faker->email, $faker->email],
        'day_of_week'        => 1,
        'time'               => $faker->time(),
        'last_ran_at'        => null,
        'locked_since'       => null
    ];
});