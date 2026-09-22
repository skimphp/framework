<?php declare(strict_types=1);

// PHP arrays only — no YAML/INI. IDE autocomplete works, no extra parser,
// configs can reference env() for 12-factor app compliance.
return [
    'name'     => env('APP_NAME', 'SKIM App'),
    'debug'     => env('APP_DEBUG', false),
    'strict_di'      => env('APP_STRICT_DI', false),
    'leak_detection' => env('APP_LEAK_DETECTION', null),
    'env'            => env('APP_ENV', 'production'),
    'key'      => env('APP_KEY', ''),
    'timezone' => 'UTC',

    // Editor used for "Open in IDE" deep-links in the error page and toolbar.
    // Supported: phpstorm, idea, webstorm, vscode, cursor, sublime, textmate, emacs, macvim, atom
    'debug_ide' => env('APP_DEBUG_IDE', 'phpstorm'),

    'session' => [
        'driver'   => 'redis',
        'lifetime' => 7200,
        'prefix'   => 'sess_',
    ],

    'log' => [
        'channel' => env('LOG_CHANNEL', 'file'),
        'level'   => env('LOG_LEVEL', 'debug'),
        'path'    => storagePath('logs/app.log'),
        'days'    => 14,
    ],

    'commands' => [
        'ext:install' => \Skim\Cli\Commands\ExtInstallCommand::class,
        'ext:list'    => \Skim\Cli\Commands\ExtListCommand::class,
        'ext:manifest' => \Skim\Cli\Commands\ExtManifestCommand::class,

        ...(env('APP_ENV') !== 'production' ? [
            'docs'          => \Skim\Dev\Docs\Commands\DocsCommand::class,
            'docs:extract'  => \Skim\Dev\Docs\Commands\DocsExtractCommand::class,
            'docs:llm'      => \Skim\Dev\Docs\Commands\DocsLlmCommand::class,
            'docs:site'     => \Skim\Dev\Docs\Commands\DocsSiteCommand::class,
            'docs:validate' => \Skim\Dev\Docs\Commands\DocsValidateCommand::class,
            'mcp:install'   => \Skim\Dev\Docs\Commands\McpInstallCommand::class,
            'mcp:serve'     => \Skim\Dev\Docs\Commands\McpServeCommand::class,
        ] : []),
    ],
];
