<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Stripe, Mailgun, SparkPost and others. This file provides a sane
    | default location for this type of information, allowing packages
    | to have a conventional place to find your various credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION'),
    ],

    'sparkpost' => [
        'secret' => env('SPARKPOST_SECRET'),
    ],

    'stripe' => [
        'model'     => App\User::class,
        'key'       => env('STRIPE_KEY'),
        'secret'    => env('STRIPE_SECRET'),
        'webhook' => [
            'secret' => env('STRIPE_WEBHOOK_SECRET'),
            'tolerance' => env('STRIPE_WEBHOOK_TOLERANCE', 300),
        ],
    ],

    'transcribe' => [
        'key'    => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION'),
    ],

    'twilio' => [
        'sid'   => env('TWILIO_SID'),
        'token' => env('TWILIO_TOKEN'),

        'test_sid'   => env('TWILIO_TEST_SID'),
        'test_token' => env('TWILIO_TEST_TOKEN'),

        'languages' => [
            'en-US' => [
                'label' => 'English (American)',
                'voices'=> [
                    'Ivy'         => 'Female - Ivy',
                    'Joanna'      => 'Female - Joanna',
                    'Salli'       => 'Female - Salli',
                    'Kendra'      => 'Female - Kendra',
                    'Kimberly'    => 'Female - Kimberly',
                    'Joey'        => 'Male - Joey',
                    'Justin'      => 'Male - Justin',
                    'Matthew'     => 'Male - Matthew'
                ]
            ],   
            'en-GB' => [
                'label' => 'English (British)',
                'voices'=> [
                    'Amy'   => 'Female - Amy',
                    'Emma'  => 'Female - Emma',
                    'Brian' => 'Male - Brian'
                ]
            ], 
            'en-AU' => [
                'label' => 'English (Australian)',
                'voices'=> [
                    'Nicole' => 'Female - Nicole',
                    'Russel' => 'Male - Russell'
                ]
            ], 
            'en-GB-WLS' => [
                'label' => 'English (Welsh)',
                'voices'=> [
                    'Geraint' => 'Male - Geraint',
                ]
            ], 
            'en-IN' => [
                'label' => 'English (Indian)',
                'voices'=> [
                    'Raveena'   => 'Female - Raveena',
                    'Aditi'     => 'Female - Aditi'
                ]
            ], 
            'es-US' => [
                'label' => 'Spanish (Latin American)',
                'voices'=> [
                    'Penelope'  => 'Female - Penelope',
                    'Miguel'    => 'Male - Miguel'
                ]
            ], 
            'es-ES' => [
                'label' => 'Spanish (Castilian)',
                'voices'=> [
                    'Conchita'  => 'Female - Conchita',
                    'Enrique'   => 'Male - Enrique'
                ]
            ], 
            'fr-FR' => [
                'label' => 'French',
                'voices'=> [
                    'Celine'    => 'Female - Celine',
                    'Mathieu'   => 'Male - Mathieu',
                ]
            ], 
            'fr-CA' => [
                'label' => 'French (Canadian)',
                'voices'=> [
                    'Chantal' => 'Female - Chantal',
                ]
            ], 
           
            'cy-GB' => [
                'label' => 'Welsh',
                'voices'=> [
                    'Gwyneth' => 'Female - Gwyneth',
                ]
            ], 
            'da-DK' => [
                'label' => 'Danish',
                'voices'=> [
                    'Naja'  => 'Female - Naja',
                    'Mads'  => 'Male - Mads',
                ]
            ], 
            'hi-IN' => [
                'label' => 'Hindi',
                'voices'=> [
                    'Aditi'         => 'Female - Aditi',
                ]
            ], 
            'de-DE' => [
                'label' => 'German',
                'voices'=> [
                    'Vicki'     => 'Female - Vicki',
                    'Marlene'   => 'Female - Marlene',
                    'Hans'      => 'Male - Hans',
                ]
            ], 
            'is-IS' => [
                'label' => 'Icelandic',
                'voices'=> [
                    'Dora'  => 'Female - Dora',
                    'Karl'  => 'Male - Karl'
                ]
            ], 
            'it-IT' => [
                'label' => 'Italian',
                'voices'=> [
                    'Carla'     => 'Female - Carla',
                    'Giorgio'   => 'Male - Giorgio',
                ]
            ], 
            'ja-JP' => [
                'label' => 'Japanese',
                'voices'=> [
                    'Mizuki'    => 'Female - Mizuki',
                    'Takumi'    => 'Male - Takumi'
                ]
            ], 
            'ko-KR' => [
                'label' => 'Korean',
                'voices'=> [
                    'Seoyeon'   => 'Female - Seoyeon',
                ]
            ], 
            'nb-NO' => [
                'label' => 'Norwegian',
                'voices'=> [
                    'Liv'  => 'Female - Liv',
                ]
            ], 
            'nl-NL' => [
                'label' => 'Dutch',
                'voices'=> [
                    'Lotte' => 'Female - Lotte',
                    'Ruben' => 'Male - Ruben',
                ]
            ], 
            'pl-PL' => [
                'label' => 'Polish',
                'voices'=> [
                    'Ewa'   => 'Female - Ewa',
                    'Jan'   => 'Female - Jan',
                    'Maja'  => 'Female - Maja',
                ]
            ], 
            'pt-BR' => [
                'label' => 'Portuguese (Brazilian)',
                'voices'=> [
                    'Vitoria'   => 'Female - Vitoria',
                    'Ricardo'   => 'Male - Ricardo'
                ]
            ], 
            'pt-PT' => [
                'label' => 'Portuguese (European)',
                'voices'=> [
                    'Ines' => 'Female - Ines',
                    'Cristiano' => 'Male - Cristiano'
                ]
            ], 
            'ro-RO' => [
                'label' => 'Romanian',
                'voices'=> [
                    'Carmen' => 'Female - Carmen',
                ]
            ], 
            'ru-RU' => [
                'label' => 'Russian',
                'voices'=> [
                    'Tatyana'   => 'Female - Tatyana',
                    'Maxim'     => 'Male - Maxim'
                ]
            ], 
            'sv-SE' => [
                'label' => 'Swedish',
                'voices'=> [
                    'Astrid'         => 'Female - Astrid',
                ]
            ],
            'tr-TR' => [
                'label' => 'Turkish',
                'voices'=> [
                    'Filiz' => 'Female - Filiz',
                ]
            ]
        ],

        'magic_numbers' => [
            'unavailable'   => '+15005550000',
            'invalid'       => '+15005550001',
            'available'     => '+15005550006'
        ]
    ]
];
