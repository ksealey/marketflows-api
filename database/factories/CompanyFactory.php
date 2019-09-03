<?php

/* @var $factory \Illuminate\Database\Eloquent\Factory */

use Faker\Generator as Faker;

$factory->define(\App\Models\Company::class, function (Faker $faker) {
    return [
        'name' => $faker->company,
        'webhook_actions' => json_encode([
            'calls.started' => [
                'url'    => 'http://' . str_random(40) . '.com/call-started',
                'method' => 'POST'
            ],
            'calls.updated' => [
                'url'    => 'http://' . str_random(40) . '.com/call-updated',
                'method' => 'POST'
            ],
            'calls.completed' => [
                'url'    => 'http://' . str_random(40) . '.com/somewhere.com/call-completed',
                'method' => 'POST'
            ]
        ])
    ];
});
