<?php declare(strict_types=1);

use Skim\Core\Config;
use Skim\Dev\Docs\Value\DocsGenerationPaths;

describe('DocsGenerationPaths', function(): void {

    test('resolves source and output flags relative to basePath', function(): void {
        $paths = \Skim\Dev\Docs\Value\DocsGenerationPaths::fromFlags([
            'source' => '.agents/skills/better-commenting/test/5',
            'output' => '.agents/skills/better-commenting/test/5/res_mdx',
        ]);

        expect($paths->scanPaths())->toBe([basePath('.agents/skills/better-commenting/test/5')]);
        expect($paths->jsonPath())->toBe(basePath('.agents/skills/better-commenting/test/5/res_mdx/llm.json'));
        expect($paths->llmMdPath())->toBe(basePath('.agents/skills/better-commenting/test/5/res_mdx/llm.md'));
        expect($paths->mdxDir())->toBe(basePath('.agents/skills/better-commenting/test/5/res_mdx'));
    });

    test('keeps config defaults when no flags are supplied', function(): void {
        \Skim\Core\Config::set('docs.scan_paths', [basePath('src')]);
        \Skim\Core\Config::set('docs.output.json', basePath('custom/llm.json'));
        \Skim\Core\Config::set('docs.output.llm_md', basePath('custom/llm.md'));
        \Skim\Core\Config::set('docs.output.mdx_dir', basePath('custom/mdx'));

        $paths = \Skim\Dev\Docs\Value\DocsGenerationPaths::fromFlags([]);

        expect($paths->scanPaths())->toBe([basePath('src')]);
        expect($paths->jsonPath())->toBe(basePath('custom/llm.json'));
        expect($paths->llmMdPath())->toBe(basePath('custom/llm.md'));
        expect($paths->mdxDir())->toBe(basePath('custom/mdx'));
    });

});
