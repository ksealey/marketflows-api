<?php
return [
    'campaigns' => [
        'campaign_targets' => [
            'device_types' => [
                'DESKTOP',
                'TABLET',
                'PHONE'
            ],
            'browsers' => [
                'GOOGLE_CHROME',
                'MICROSOFT_EDGE',
                'FIREFOX',
                'SAFARI',
                'IE',
                'OPERA',
                'NETSCAPE',
                'OTHER'
            ],
            'url_rules' => [
                'drivers' => [
                    'ENTRY_URL',
                    'HAS_VISITED_URL',
                    'CURRENT_URL'
                ],
                'types' => [
                    'PATH',
                    'PARAM'
                ],
                'condition_types' => [
                    'EQUALS',
                    'CONTAINS',
                    'MATCHES'
                ]
            ]
        ],
        'campaign_number_swaps' => [
            'condition_types' => [
                'SELECTOR_MATCHES',
                'NUMBER_MATCHES',
                'NUMBER_NOT_MATCHES',
                'ALL'
            ]
        ]

    ]
];