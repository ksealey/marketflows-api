<?php

/* @var $factory \Illuminate\Database\Eloquent\Factory */

use App\Model;
use Faker\Generator as Faker;

$factory->define(\Tests\Models\TwilioRecording::class, function (Faker $faker) {
    return [
        'CallSid'               => str_random(40),
        'RecordingSid'          => str_random(40),
        'RecordingUrl'          => '',
        'RecordingStatus'       => 'completed',
        'RecordingDuration'     => mt_rand(9, 999),
        'RecordingChannels'     => mt_rand(1, 2),
        'RecordingStartTime'    => date('U'),
        'RecordingSource'       => 'DialVerb'
    ];
});