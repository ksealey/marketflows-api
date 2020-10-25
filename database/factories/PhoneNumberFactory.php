<?php

/* @var $factory \Illuminate\Database\Eloquent\Factory */

use App\Model;
use Faker\Generator as Faker;
use \App\Models\Company\PhoneNumber;

$factory->define(PhoneNumber::class, function (Faker $faker) {
    $categories = ['ONLINE', 'OFFLINE'];
    $category   = $categories[mt_rand(0, 1)];
    
    if( $category === 'ONLINE' ){
        $subCategories = PhoneNumber::ONLINE_SUB_CATEGORIES;
    }else{
        $subCategories = PhoneNumber::OFFLINE_SUB_CATEGORIES;
    }
    
    $subCategory = $subCategories[mt_rand(0, count($subCategories) - 1)];
    
    $type = mt_rand(0, 1) ? PhoneNumber::TYPE_LOCAL : PhoneNumber::TYPE_TOLL_FREE;

    return [
        'uuid'                      => $faker->uuid(),
        'external_id'               => str_random(40),
        'category'                  => $category,
        'sub_category'              => $subCategory,
        'type'                      => $type,
        'name'                      => $faker->realText(20),
        'country_code'              => '1', 
        'number'                    => substr($faker->e164PhoneNumber, -10),
        'voice'                     => true,
        'sms'                       => true,
        'mms'                       => true,
        'medium'                    => $faker->realText(10),
        'content'                   => $faker->realText(10),
        'campaign'                  => $faker->realText(10),
        'source'                    => $faker->realText(10),
        'is_paid'                   => mt_rand(0,1),
        'is_organic'                => mt_rand(0,1),
        'is_direct'                 => mt_rand(0,1),
        'is_referral'               => mt_rand(0,1),
        'is_search'                 => mt_rand(0,1),
        'last_assigned_at'          => null,
        'purchased_at'              => now(),
        'swap_rules'                => json_encode([
            'targets' => [
                str_replace('+', '', $faker->e164PhoneNumber)
            ],
            'device_types'  => ['ALL'],
            'browser_types' => ['ALL'],
            'inclusion_rules' => [
                [
                    'rules' => [
                        [
                            'type' => 'ALL'
                        ]
                    ]
                ]
            ],
            'exclusion_rules' => [],
        ])
    ];
});
