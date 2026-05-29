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

    'commands' => [
        'ext:install' => \skim\cli\commands\ext_install_command::class,
        'ext:list'    => \skim\cli\commands\ext_list_command::class,
        'ext:manifest' => \skim\cli\commands\ext_manifest_command::class,

        ...(env('APP_ENV') !== 'production' ? [
            'docs'          => \skim\dev\docs\commands\docs_command::class,
            'docs:extract'  => \skim\dev\docs\commands\docs_extract_command::class,
            'docs:llm'      => \skim\dev\docs\commands\docs_llm_command::class,
            'docs:site'     => \skim\dev\docs\commands\docs_site_command::class,
            'docs:validate' => \skim\dev\docs\commands\docs_validate_command::class,
            'mcp:serve'     => \skim\dev\docs\commands\mcp_serve_command::class,
        ] : []),
    ],
];
