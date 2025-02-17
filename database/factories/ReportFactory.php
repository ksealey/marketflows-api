<?php

/* @var $factory \Illuminate\Database\Eloquent\Factory */

use App\Model;
use Faker\Generator as Faker;

$factory->define(\App\Models\Company\Report::class, function (Faker $faker) {
    return [
        'name'  => $faker->company,
        'module'=> 'calls',
        'type'  => 'bar',
        'group_by' => 'calls.source',
        'date_type' => 'LAST_N_DAYS',
        'last_n_days' => mt_rand(1, 730),
        'start_date'  => null,
        'end_date'    => null,
        'conditions'  => null,
        'vs_previous_period' => 0
    ];
});
