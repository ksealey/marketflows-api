<?php

/* @var $factory \Illuminate\Database\Eloquent\Factory */

use App\Model;
use Faker\Generator as Faker;

$factory->define(\App\Models\Company\CampaignDomain::class, function (Faker $faker) {
    return [
        'uuid'   => $faker->uuid(),
        'domain' => 'http' . (mt_rand(0,1) ? 's' : '') . '://' . (mt_rand(0,1) ? 'www' : '') . '.' . str_random(20) . '.com' 
    ];
});
