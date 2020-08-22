<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\Model;
use Faker\Generator as Faker;

$factory->define(\App\Models\SupportTicketAttachment::class, function (Faker $faker) {
    return [
        'file_type'     => $faker->mimeType,
        'file_size'     => mt_rand(1000, 2000),
        'path'          => $faker->file()
    ];
});
