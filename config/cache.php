<?php declare(strict_types=1);

// APCu excluded: shared-nothing architecture — APCu state is per-process and
// inconsistent under PHP-FPM with multiple workers. Redis is the safe default.
return [
    'driver' => env('CACHE_DRIVER', 'redis'),   // redis | file | array
    'prefix' => 'skim_',
    'ttl'    => 3600,

    'redis' => [
        'host'     => env('REDIS_HOST', '127.0.0.1'),
        'port'     => (int) env('REDIS_PORT', 6379),
        'password' => env('REDIS_PASS', null),
        'database' => (int) env('REDIS_DB', 0),
    ],

    'file' => [
        'path' => storagePath('cache'),
    ],

    // fallback: if Redis is unreachable, silently switch to file driver.
    // Prevents cache failure from cascading into app failure.
    'fallback' => 'file',
];
