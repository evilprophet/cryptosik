<?php

return [
    'default' => env('MAIL_MAILER', mb_strtolower((string) env('APP_MODE', 'prod')) === 'dev' ? 'log' : 'smtp'),

    'mailers' => [
        'smtp' => [
            'transport' => 'smtp',
            'url' => env('MAIL_URL'),
            'host' => env('MAIL_HOST', '127.0.0.1'),
            'port' => env('MAIL_PORT', 587),
            'username' => env('MAIL_USERNAME'),
            'password' => env('MAIL_PASSWORD'),
            'timeout' => null,
            'local_domain' => parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST),
            'encryption' => env('MAIL_ENCRYPTION', 'tls'),
        ],

        'log' => [
            'transport' => 'log',
            'channel' => env('MAIL_LOG_CHANNEL'),
        ],

        'array' => [
            'transport' => 'array',
        ],
    ],

    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
        'name' => env('MAIL_FROM_NAME', env('APP_NAME', 'Cryptosik')),
    ],
];
