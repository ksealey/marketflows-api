<?php

/* @var $factory \Illuminate\Database\Eloquent\Factory */

use App\Model;
use Faker\Generator as Faker;

$factory->define(\App\Models\Company\Report::class, function (Faker $faker) {
    return [
        'name'          =>  $faker->realText(16),
        'module'        => 'calls',
        'metric'        => 'calls.source',
        'metric_order'  => 'desc',
        'timezone'      => $faker->timezone,
        'date_type'     => 'CUSTOM',
        'start_date'    => now()->subDays(10),
        'end_date'      => now()->subDays(1),
        'comparisons'   => [],
        'conditions'    => [],
       'is_system_report' => 0
    ];
});
