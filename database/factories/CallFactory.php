<?php

/* @var $factory \Illuminate\Database\Eloquent\Factory */

use App\Model;
use Faker\Generator as Faker;
use \App\Models\Company\PhoneNumber;

$factory->define(\App\Models\Company\Call::class, function (Faker $faker) {
    return [
        'type'        => mt_rand(0,1) ? PhoneNumber::TYPE_LOCAL : PhoneNumber::TYPE_TOLL_FREE,
        'category'    => 'Online',
        'sub_category' => 'Website',

        'external_id' => str_random(40),
        'direction'   => 'inbound',
        'status'      => 'completed',

        'duration'    => mt_rand(30, 500),

        'source'      => $faker->realText(40),
        'medium'      => $faker->realText(40),
        'content'     => $faker->realText(40),
        'campaign'    => $faker->realText(40),
        'is_paid'     => mt_rand(0,1),
        'is_organic'  => mt_rand(0,1),
        'is_direct'   => mt_rand(0,1),
        'is_referral' => mt_rand(0,1),
        'is_search'   => mt_rand(0,1),
        

        'recording_enabled' => mt_rand(0,1) ? 1 : 0,
        'forwarded_to' => str_replace('+', '', $faker->e164PhoneNumber),

        'transcription_enabled' => 1
    ];
});
