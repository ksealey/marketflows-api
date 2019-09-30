<?php

/* @var $factory \Illuminate\Database\Eloquent\Factory */

use App\Model;
use Faker\Generator as Faker;

$factory->define(\App\Models\Company\PhoneNumberPoolProvisionRule::class, function (Faker $faker) {
    $areaCodes = [
        '813', '727', '678', '319'
    ];

    return [
        'country'   => 'US',
        'area_code' => $areaCodes[array_rand($areaCodes)],
        'priority'  => 0,
    ];
});
