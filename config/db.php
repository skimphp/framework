<?php declare(strict_types=1);

return [
    'default' => [
        'driver'   => env('DB_DRIVER', 'mysql'),
        'host'     => env('DB_HOST', 'localhost'),
        'port'     => (int) env('DB_PORT', 3306),
        'database' => env('DB_NAME', 'myapp'),
        'user'     => env('DB_USER', 'root'),
        'password' => env('DB_PASS', ''),
        'charset'  => 'utf8mb4',
    ],

    // Uncomment and configure for a secondary alt connection:
    // 'alt' => [
    //     'driver'   => 'pgsql',
    //     'host'     => env('ALT_DB_HOST', 'localhost'),
    //     'port'     => (int) env('ALT_DB_PORT', 5432),
    //     'database' => env('ALT_DB_NAME', 'alt'),
    //     'user'     => env('ALT_DB_USER', 'analyst'),
    //     'password' => env('ALT_DB_PASS', ''),
    // ],
];
