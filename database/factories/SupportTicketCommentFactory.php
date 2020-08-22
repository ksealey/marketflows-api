<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\Model;
use Faker\Generator as Faker;

$factory->define(\App\Models\SupportTicketComment::class, function (Faker $faker) {
    return [
       'comment'    => $faker->realText(1000)
    ];
});
