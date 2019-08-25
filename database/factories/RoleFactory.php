<?php

/* @var $factory \Illuminate\Database\Eloquent\Factory */

use App\Model;
use Faker\Generator as Faker;

$factory->define(\App\Models\Role::class, function (Faker $faker) {
    return [
        'name'   => $faker->realText(10),
        'policy' => json_encode([
            'policy' => [
                [
                    'module'        => 'reports',
                    'permissions'   => '*'
                ],
                [
                    'module'        => 'companies',
                    'permissions'   => 'read'
                ],
                [
                    'module'        => 'payment-methods',
                    'permissions'   => 'create,read,update'
                ],
            ]
        ])
    ];
});
