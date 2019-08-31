<?php

/* @var $factory \Illuminate\Database\Eloquent\Factory */

use App\Model;
use Faker\Generator as Faker;

$factory->define(\App\Models\Company\CampaignSpend::class, function (Faker $faker) {
    return [
        'from_date' => date('Y-m-d', strtotime('now -' . mt_rand(5, 10) . ' days')),
        'to_date'   => date('Y-m-d', strtotime('now -' . mt_rand(0, 4) . ' days')),
        'total'     => floatval(mt_rand(100, 99999) . '.' . mt_rand(0, 99))
    ];
});
