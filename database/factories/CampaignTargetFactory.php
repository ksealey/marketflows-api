<?php

/* @var $factory \Illuminate\Database\Eloquent\Factory */

use App\Model;
use Faker\Generator as Faker;

$factory->define(\App\Models\Company\CampaignTarget::class, function (Faker $faker) {
    return [
        'rules' => json_encode([
            'DEVICE_TYPES' => [
                'DESKTOP',
                'TABLET',
                'PHONE',
            ],

            'BROWSERS' => [
                'IE',
                'GOOGLE_CHROME'
            ],

            'LOCATIONS' => [],

            'URL_RULES' => [
                [
                    'name'   => $faker->realText(20),
                    'driver' => 'ENTRY_URL',
                    'type'   => 'PATH',
                    'condition' => [
                        'type'  => 'EQUALS',
                        'key'   => '/home',
                        'value' => ''
                    ]
                ]
            ]
        ])
    ];
});
