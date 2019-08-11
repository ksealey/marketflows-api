<?php

/* @var $factory \Illuminate\Database\Eloquent\Factory */

use App\Model;
use Faker\Generator as Faker;

$factory->define(\App\Models\AudioClip::class, function (Faker $faker) {
    return [
        'name'     => $faker->company,
        'path'     => '/audio_clips/' . mt_rand(9, 9999999) . '.mp3',
        'mime_type'=> 'audio/mpeg'
    ];
});
