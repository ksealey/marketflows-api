<?php

/* @var $factory \Illuminate\Database\Eloquent\Factory */

use App\Model;
use Faker\Generator as Faker;
use \App\Models\Account;

$factory->define(Account::class, function (Faker $faker) {
    $config    = config('services.twilio.languages');
    $languages = array_keys($config);
    $language  = $languages[array_rand($languages)];
    $voices    = array_keys($config[$language]['voices']); 
    $voice     = $voices[array_rand($voices)];
    return [
        'name'           => $faker->company,
        'tts_language'   => $language,
        'tts_voice'      => $voice,
        'source_param'   => 'utm_source,source',
        'medium_param'   => 'utm_medium,medium',
        'content_param'  => 'utm_content,content',
        'campaign_param' => 'utm_campaign,content',
        'keyword_param'  => 'utm_term,keyword,term',
    ];
});
