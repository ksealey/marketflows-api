<?php

/* @var $factory \Illuminate\Database\Eloquent\Factory */

use App\Model;
use Faker\Generator as Faker;
use \App\Models\Company\PhoneNumber;

$factory->define(\App\Models\Company\CallRecording::class, function (Faker $faker) {
    return [
        'external_id'   => str_random(32),
        'duration'      => mt_rand(10, 400),
        'file_size'     => mt_rand(1000, 10000),
        'path'          => '/call_recordings/' . str_random(32). '.mp3'
    ];
});
