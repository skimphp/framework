<?php declare(strict_types=1);

return [
    'scan_paths' => [
        basePath('src'),
    ],

    // When true, prepends vendor/skim/framework/llm.md into the generated llm.md.
    // Set false when developing skim itself (this IS the framework).
    // Set true when building an app on top of skim (Scenario 1 / 2).
    'include_framework_context' => false,

    'output' => [
        'json'    => basePath('llm.json'),
        'llm_md'  => basePath('llm.md'),
        'mdx_dir' => basePath('docs/src/content/docs/api'),
    ],

    'validate' => [
        'min_coverage' => 0.8,  // fraction of public methods that must carry at least one @ai.* tag
    ],
];
