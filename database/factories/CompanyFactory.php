<?php

/* @var $factory \Illuminate\Database\Eloquent\Factory */

use Faker\Generator as Faker;

$factory->define(\App\Models\Company::class, function (Faker $faker) {
    return [
        'name'           => $faker->company,
        'industry'       => 'Manufacturing',
        'country'        => 'US',
        'tts_language'   => 'en-US',
        'tts_voice'      => 'Joanna',
        'source_param'   => 'utm_source,source',
        'medium_param'   => 'utm_medium,medium',
        'content_param'  => 'utm_content,content',
        'campaign_param' => 'utm_campaign,content',
        'keyword_param'  => 'utm_term,keyword,term',
    ]; 
});
