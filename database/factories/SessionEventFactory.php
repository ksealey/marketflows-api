<?php

/* @var $factory \Illuminate\Database\Eloquent\Factory */

use App\Model;
use Faker\Generator as Faker;

$factory->define(App\Models\Events\SessionEvent::class, function (Faker $faker) {
    return [
        'event_type' => 'PageView',
        'content'    => $faker->url,
        'created_at' => now()
    ];
});
