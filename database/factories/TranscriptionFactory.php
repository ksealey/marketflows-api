<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\Model;
use Faker\Generator as Faker;

$factory->define(App\Models\Company\Transcription::class, function (Faker $faker) {
    return [
        'text' => json_encode([
            [
                'caller' => $faker->realText(50), 
                'speaker' => $faker->realText(50)
            ],
            [
                'caller' => $faker->realText(50), 
                'speaker' => $faker->realText(50)
            ],
            [
                'caller' => $faker->realText(50), 
                'speaker' => $faker->realText(50)
            ]
        ])
    ];
});
