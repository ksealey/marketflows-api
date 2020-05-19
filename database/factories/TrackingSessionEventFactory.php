<?php

/* @var $factory \Illuminate\Database\Eloquent\Factory */

use App\Model;
use Faker\Generator as Faker;

$factory->define(App\Models\TrackingSessionEvent::class, function (Faker $faker) {
    return [
        'event_type' => 'PageView',
        'content'    => $faker->url,
        'created_at' => (new DateTime())->format('Y-m-d H:i:s.u')
    ];
});
