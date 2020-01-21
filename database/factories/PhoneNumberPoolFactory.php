<?php

/* @var $factory \Illuminate\Database\Eloquent\Factory */

use App\Model;
use Faker\Generator as Faker;

$factory->define(\App\Models\Company\PhoneNumberPool::class, function (Faker $faker) {
    return [
        'name'                      => $faker->company,
        'source'                    => $faker->company,
        'source_param'              => 'utm_source',
        'category'                  => 'ONLINE',
        'sub_category'              => 'WEBSITE_SESSION',
        'referrer_aliases'          => [
            'aliases' => [
                [
                    'domain' => 'google.com',
                    'alias'  =>  'Google Organic'
                ],
                [
                    'domain' => 'facebook.com',
                    'alias'  => 'Facebook Organic',
                ],
                [
                    'domain' => 'yahoo.com',
                    'alias'  => 'Yahoo Organic',
                ],
                [
                    'domain' => 'bing.com',
                    'alias'  => 'Bing Organic',
                ]
            ]
        ],
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
                        ],
                        [
                            'type'=> 'LANDING_PARAM',
                            'operator' => 'CONTAINS',
                            'match_input' => [
                                'key'     => 'utm_source',
                                'value'   => 'testing'
                            ]
                        ]
                    ]
                ]
            ]

        ],
    ];
});
