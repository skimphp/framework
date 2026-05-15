<?php declare(strict_types=1);

// PHP arrays only — no YAML/INI. IDE autocomplete works, no extra parser,
// configs can reference env() for 12-factor app compliance.
return [
    'name'     => env('APP_NAME', 'SKIM App'),
    'debug'    => env('APP_DEBUG', false),
    'env'      => env('APP_ENV', 'production'),
    'key'      => env('APP_KEY', ''),
    'timezone' => 'UTC',

    'session' => [
        'driver'   => 'redis',
        'lifetime' => 7200,
        'prefix'   => 'sess_',
    ],

    'log' => [
        'channel' => env('LOG_CHANNEL', 'file'),
        'level'   => env('LOG_LEVEL', 'debug'),
        'path'    => storage_path('logs/app.log'),
        'days'    => 14,
    ],

    'commands' => [],
];
