<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\Model;
use Faker\Generator as Faker;
use \App\Models\BillingStatementItem;

$factory->define(BillingStatementItem::class, function (Faker $faker) {
    $quantity = mt_rand(1, 4);
    $price    = mt_rand(1, 10) * 1.01;

    return [
        'label'     => $faker->realText(10),
        'quantity'  => $quantity,
        'price'     => $price,
        'total'     => $quantity * $price
    ];
});
