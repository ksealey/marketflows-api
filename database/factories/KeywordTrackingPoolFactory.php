<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\Model;
use Faker\Generator as Faker;

$factory->define(\App\Models\Company\KeywordTrackingPool::class, function (Faker $faker) {
    return [
        'uuid' => $faker->uuid,
        'name' => $faker->realText(40),
        'swap_rules'                => json_encode([
            'targets' => [
                str_replace('+', '', $faker->e164PhoneNumber)
            ],
            'device_types'  => ['ALL'],
            'browser_types' => ['ALL'],
            'inclusion_rules' => [
                [
                    'rules' => [
                        [
                            'type' => 'ALL'
                        ]
                    ]
                ]
            ],
            'exclusion_rules' => [],
            'expiration_days' => 30,
        ])
    ];
});
