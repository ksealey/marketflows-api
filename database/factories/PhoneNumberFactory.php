<?php

/* @var $factory \Illuminate\Database\Eloquent\Factory */

use App\Model;
use Faker\Generator as Faker;

$factory->define(\App\Models\Company\PhoneNumber::class, function (Faker $faker) {
    return [
        'name'                      => $faker->ein,
        'country_code'              => 1,
        'number'                    => substr($faker->e164PhoneNumber, -10),
        'voice'                     => true,
        'sms'                       => true,
        'mms'                       => true,
        'external_id'               => str_random(40),
        'uuid'                      => $faker->uuid(),
        'external_id'               => str_random(40),
        'category'                  => 'ONLINE',
        'sub_category'              => 'WEBSITE',
        'type'                      => 'Local',
        'country_code'              => '1',
        'number'                    => substr($faker->e164PhoneNumber, -10),
        'voice'                     => true,
        'sms'                       => true,
        'mms'                       => true,
        'name'                      => $faker->realText(20),
        'source'                    => ['Google', 'Facebook', 'Yahoo'][mt_rand(0, 2)],
        'swap_rules'                => [
            'targets' => [
                '813557####',
                '18003098829'
            ],
            'inclusion_rules' => [
                [
                    'rules' => [
                        [
                            'type'     => 'ALL'
                        ],
                        [
                            'type'     => 'LANDING_PARAM',
                            'operator' => 'CONTAINS',
                            'match_input' => [
                                'key'   => 'utm_source',
                                'value' => 'Facebook'
                            ]
                        ]
                    ]
                ],

                [
                    'rules' => [
                        [
                            'type'     => 'LANDING_PARAM',
                            'operator' => 'CONTAINS',
                            'match_input' => [
                                'key'   => 'utm_source',
                                'value' => 'Google'
                            ]
                        ]
                    ]
                ]
            ],
            'exclusion_rules' => [
                [
                    'rules' => [
                        [
                            'type'=> 'LANDING_PARAM',
                            'operator' => 'CONTAINS',
                            'match_input' => [
                                'key'     => 'utm_source',
                                'value'   => 'test'
                            ]
                        ]
                    ]
                ]
            ]

        ],
        'last_assigned_at'          => null
    ];
});
