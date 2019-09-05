<?php

/* @var $factory \Illuminate\Database\Eloquent\Factory */

use App\Model;
use Faker\Generator as Faker;

$factory->define(\App\Models\Company\WebSourceField::class, function (Faker $faker) {
    $params = [
        'utm_source',
        'utm_campaign',
        'utm_creative',
        'utm_content'
    ];
    return [
        'label'             => $faker->realText(10),
        'url_parameter'     => $params[mt_rand(0, count($params) - 1)],
        'default_value'     => mt_rand(0, 1) ? $faker->realText(10) : null, // When there is an http refferrer btut this is not set or empty
        'direct_value'      => 'direct'
    ];
});
