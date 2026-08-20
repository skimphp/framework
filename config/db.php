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

    // Uncomment and configure for a secondary analytics connection:
    // 'analytics' => [
    //     'driver'   => 'pgsql',
    //     'host'     => env('ANALYTICS_DB_HOST', 'localhost'),
    //     'port'     => (int) env('ANALYTICS_DB_PORT', 5432),
    //     'database' => env('ANALYTICS_DB_NAME', 'analytics'),
    //     'user'     => env('ANALYTICS_DB_USER', 'analyst'),
    //     'password' => env('ANALYTICS_DB_PASS', ''),
    // ],
];
