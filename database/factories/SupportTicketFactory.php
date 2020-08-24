<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\Model;
use Faker\Generator as Faker;
use \App\Models\SupportTicket;

$factory->define(SupportTicket::class, function (Faker $faker) {
    $urgencies = SupportTicket::urgencies();

    return [
        'urgency'       => $urgencies[mt_rand(0, count($urgencies) -1)],
        'subject'       => $faker->realText(200),
        'description'   => $faker->realText(1000),
        'status'        => SupportTicket::STATUS_UNASSIGNED
    ];
});
