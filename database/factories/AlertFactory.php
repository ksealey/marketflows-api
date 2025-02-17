<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\Model;
use Faker\Generator as Faker;
use \App\Models\Alert;

$factory->define(Alert::class, function (Faker $faker) {
    return [
        'type'      => Alert::TYPE_DEFAULT,
        'category'  => Alert::CATEGORY_PAYMENT,
        'title'     => str_random(40),
        'message'   => $faker->realText(150),
    ];
});
