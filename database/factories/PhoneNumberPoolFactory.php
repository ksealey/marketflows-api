<?php

/* @var $factory \Illuminate\Database\Eloquent\Factory */

use App\Model;
use Faker\Generator as Faker;

$factory->define(\App\Models\Company\PhoneNumberPool::class, function (Faker $faker) {
    return [
        'name'                      => $faker->company,
        'swap_rules'                => json_encode([
            'targets' => [
                '18003098829'
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
            'exclusion_rules' => []
        ])
    ];
});
